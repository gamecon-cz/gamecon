<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\DrivejsiTexty;
use Gamecon\Aktivita\Korektura\KorekturaTextu;
use Gamecon\Aktivita\Korektura\OdpovedAi;
use Gamecon\Aktivita\Korektura\SouborKorektur;
use Gamecon\Aktivita\Korektura\StavKorekturyEnum;
use Gamecon\Aktivita\Korektura\TextyAktivity;
use PHPUnit\Framework\TestCase;

class SouborKorekturTest extends TestCase
{
    /**
     * @test
     */
    public function souborMaPokynyATextyVeZnackach(): void
    {
        $soubor = (new SouborKorektur())->vytvor(
            [$this->korektura(7, new TextyAktivity('Krycí jména', 'Hra se slovy', "Řádek 1\r\n\r\nŘádek 2\n"))],
            [],
            2026,
        );

        self::assertStringContainsString('# Korektura textů aktivit GameConu 2026', $soubor);
        self::assertStringContainsString(<<<'TEXT'
<gc-aktivita id="7">
<gc-nazev>Krycí jména</gc-nazev>
<gc-popis-kratky>Hra se slovy</gc-popis-kratky>
<gc-popis>
Řádek 1

Řádek 2
</gc-popis>
</gc-aktivita>
TEXT,
            $soubor,
        );
        self::assertStringNotContainsString('<gc-drivejsi rok', $soubor);
    }

    /**
     * A chat writes a long answer as text by itself, to be copied by hand; asked for a file, it gives one named
     * after the package. The API answers with text whatever it is asked, so its prompt does not ask for a file.
     *
     * @test
     */
    public function chatDostanePokynVratitOdpovedJakoSouborApiNe(): void
    {
        $souborKorektur = new SouborKorektur();
        $soubor = $souborKorektur->vytvor(
            [$this->korektura(7, new TextyAktivity('Krycí jména', 'Hra se slovy', 'Popis'))],
            [],
            2026,
            3,
        );

        self::assertStringContainsString('ulož do textového souboru korektura-gamecon-2026-balik-3-odpoved.txt', $soubor);
        self::assertLessThan(
            strpos($soubor, '## Texty ke korektuře'),
            strpos($soubor, '## Odpověď jako soubor'),
            'Pokyn k souboru je před texty',
        );
        self::assertStringContainsString(
            'korektura-gamecon-2026-odpoved.txt',
            $souborKorektur->vytvor([], [], 2026),
            'Všechny balíky v jednom souboru',
        );
        self::assertTrue(SouborKorektur::jeBalik($soubor));

        $pokynyApi = $souborKorektur->pokyny(2026);
        self::assertStringNotContainsString('Odpověď jako soubor', $pokynyApi);
        self::assertStringEndsWith('## Texty ke korektuře', $pokynyApi);
        self::assertStringStartsWith('# Korektura textů aktivit GameConu 2026', $pokynyApi);
    }

    /**
     * @test
     */
    public function drivejsiVerzeJeUAktivityVeVlastnimBloku(): void
    {
        $soubor = (new SouborKorektur())->vytvor(
            [$this->korektura(7, new TextyAktivity('Krycí jména', 'Hra se slovy', 'Letos'))],
            [
                7 => new DrivejsiTexty(2025, new TextyAktivity('Krycí jména', 'Hra se slovy', 'Loni')),
            ],
            2026,
        );

        self::assertStringContainsString(<<<'TEXT'
<gc-drivejsi rok="2025">
<gc-nazev>Krycí jména</gc-nazev>
<gc-popis-kratky>Hra se slovy</gc-popis-kratky>
<gc-popis>
Loni
</gc-popis>
</gc-drivejsi>
</gc-aktivita>
TEXT,
            $soubor,
        );
    }

    /**
     * An AI that echoes the input back unchanged must not produce a single proposal, the earlier version included.
     *
     * @test
     */
    public function vracenyVstupNedaZadnouZmenu(): void
    {
        $texty = new TextyAktivity('Krycí jména', 'Hra se slovy', "Popis\n\n- odrážka");
        $soubor = (new SouborKorektur())->vytvor(
            [$this->korektura(7, $texty)],
            [
                7 => new DrivejsiTexty(2025, new TextyAktivity('Krycí jména', 'Jiný krátký', 'Jiný popis')),
            ],
            2026,
        );

        $navrh = OdpovedAi::rozparsuj($soubor)->navrhy[7];

        self::assertSame($texty->nazev, $navrh->nazev);
        self::assertSame($texty->popisKratky, $navrh->popisKratky);
        self::assertSame($texty->popis, TextyAktivity::normalizuj($navrh->popis));
    }

    /**
     * Real texts of activities (markdown, HTML, CRLF, Slovak and English, long descriptions): the file is the one
     * saved, and an AI that echoes it back finds nothing to change in any of them.
     *
     * @test
     */
    public function vzorekSkutecnychTextuDaUlozenySouborAZpetSePrecteBezZmen(): void
    {
        $korektury = [];
        foreach (json_decode(
            (string) file_get_contents(__DIR__ . '/data/texty-aktivit-vzorek.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        ) as $aktivita) {
            $korektury[] = $this->korektura(
                (int) $aktivita['id_akce'],
                new TextyAktivity($aktivita['nazev'], $aktivita['popis_kratky'], $aktivita['popis']),
            );
        }

        $soubor = (new SouborKorektur())->vytvor($korektury, [], 2022);

        self::assertSame(
            file_get_contents(__DIR__ . '/data/korektura-vzorek.txt'),
            $soubor,
            'Soubor pro AI se změnil. Je-li to záměr (pokyny, formát), ulož tenhle výstup do data/korektura-vzorek.txt.',
        );
        $odpoved = OdpovedAi::rozparsuj($soubor);
        self::assertSame([], $odpoved->varovani);
        foreach ($korektury as $korektura) {
            $navrh = $odpoved->navrhy[$korektura->idAktivity];
            $puvodni = $korektura->puvodni->normalizovane();
            self::assertSame($puvodni->nazev, $navrh->nazev, "Název aktivity {$korektura->idAktivity}");
            self::assertSame($puvodni->popisKratky, $navrh->popisKratky, "Krátký popis aktivity {$korektura->idAktivity}");
            self::assertSame($puvodni->popis, TextyAktivity::normalizuj((string) $navrh->popis), "Popis aktivity {$korektura->idAktivity}");
        }
    }

    private function korektura(int $idAktivity, TextyAktivity $texty): KorekturaTextu
    {
        return new KorekturaTextu(
            id: 1,
            idAktivity: $idAktivity,
            balik: 1,
            stav: StavKorekturyEnum::EXPORTOVANO,
            puvodni: $texty,
            navrhNazev: null,
            navrhPopisKratky: null,
            navrhPopis: null,
            zmeny: null,
        );
    }
}
