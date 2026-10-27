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
