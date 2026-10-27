<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Aktivita\Korektura\KorekturyTextu;
use Gamecon\Aktivita\Korektura\KorekturyTextuRepository;
use Gamecon\Aktivita\Korektura\StavKorekturyEnum;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class KorekturyTextuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;
    private KorekturyTextuRepository $repository;
    private KorekturyTextu $korektury;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $this->repository = new KorekturyTextuRepository();
        $this->korektury = new KorekturyTextu($this->systemoveNastaveni, $this->repository);
    }

    /**
     * @test
     */
    public function exportVezmeNeopraveneVerejneAktivityRocnikuJednouZaRodinuInstanci(): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $neopravena = $this->vytvorAktivitu('Neopravená');
        $opravena = $this->vytvorAktivitu('Opravená', probehlaKorekce: true);
        $technicka = $this->vytvorAktivitu('Technická', typ: TypAktivity::TECHNICKA);
        $loni = $this->vytvorAktivitu('Loňská', rok: $rocnik - 1);
        [$hlavni, $dalsiInstance] = $this->vytvorRodinuInstanci('Rodina');

        $kExportu = array_keys($this->repository->dejTextyKExportu($rocnik));

        self::assertContains($neopravena, $kExportu);
        self::assertContains($hlavni, $kExportu);
        self::assertNotContains($dalsiInstance, $kExportu, 'Instance sdílejí texty, stačí hlavní aktivita');
        self::assertNotContains($opravena, $kExportu);
        self::assertNotContains($technicka, $kExportu);
        self::assertNotContains($loni, $kExportu);

        $baliky = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);

        self::assertCount(1, $baliky);
        self::assertNotContains(
            $neopravena,
            array_keys($this->repository->dejTextyKExportu($rocnik)),
            'Exportovaná aktivita se znovu nevyexportuje',
        );
        self::assertStringContainsString(
            '<gc-aktivita id="' . $neopravena . '">',
            $this->korektury->soubor($baliky[0]),
        );
    }

    /**
     * @test
     */
    public function prijatyNavrhZapiseTextyCeleRodineAOznaciJiJakoOpravenou(): void
    {
        [$hlavni, $dalsiInstance] = $this->vytvorRodinuInstanci('Rodina s chybou');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);

        $vysledek = $this->korektury->importuj(<<<TEXT
<gc-aktivita id="{$hlavni}">
<gc-popis>Popis, který je opravený.</gc-popis>
<gc-zmeny>čárka</gc-zmeny>
</gc-aktivita>
TEXT,
        );

        self::assertSame(1, $vysledek->pocetNavrhu);
        self::assertSame(0, $vysledek->pocetBezZmen);
        self::assertSame([], $vysledek->varovani);

        $korektura = $this->repository->dejOtevrene([$hlavni])[$hlavni];
        self::assertSame(StavKorekturyEnum::NAVRZENO, $korektura->stav);
        self::assertNull($korektura->navrhNazev);
        self::assertSame('Popis, který je opravený.', $korektura->navrhPopis);
        self::assertSame('čárka', $korektura->zmeny);

        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);

        foreach ([$hlavni, $dalsiInstance] as $idAktivity) {
            $radek = dbOneLine('SELECT nazev_akce, popis, probehla_korekce FROM akce_seznam WHERE id_akce = $0', [$idAktivity]);
            self::assertSame('Rodina s chybou', $radek['nazev_akce']);
            self::assertSame('Popis, který je opravený.', $radek['popis']);
            self::assertSame(1, (int) $radek['probehla_korekce']);
        }
        self::assertSame(StavKorekturyEnum::PRIJATO, $this->repository->dej($korektura->id)->stav);
    }

    /**
     * @test
     */
    public function navrhTextuZmenenehoOdExportuNejdePrijmout(): void
    {
        $idAktivity = $this->vytvorAktivitu('Měněná');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-popis>Opravený popis.</gc-popis></gc-aktivita>");
        dbQuery('UPDATE akce_seznam SET popis = $0 WHERE id_akce = $1', ['Pořadatel to mezitím přepsal.', $idAktivity]);

        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->expectException(KorekturuNelzeVyridit::class);
        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);
    }

    /**
     * @test
     */
    public function importPreskociNeexportovanouAktivituAStejnyTextNeniZmena(): void
    {
        $exportovana = $this->vytvorAktivitu('Exportovaná');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $neexportovana = $this->vytvorAktivitu('Až po exportu');

        $vysledek = $this->korektury->importuj(<<<TEXT
<gc-aktivita id="{$exportovana}">
<gc-nazev>Exportovaná</gc-nazev>
<gc-popis>
Popis aktivity Exportovaná.
</gc-popis>
<gc-zmeny>nic</gc-zmeny>
</gc-aktivita>
<gc-aktivita id="{$neexportovana}"><gc-nazev>Jiný</gc-nazev></gc-aktivita>
TEXT,
        );

        self::assertSame(1, $vysledek->pocetNavrhu);
        self::assertSame(1, $vysledek->pocetBezZmen);
        self::assertCount(1, $vysledek->varovani);
        self::assertStringContainsString((string) $neexportovana, $vysledek->varovani[0]);

        $korektura = $this->repository->dejOtevrene([$exportovana])[$exportovana];
        self::assertFalse($korektura->maNavrzeneZmeny());
        self::assertNull($korektura->zmeny, 'Bez změn není co popisovat');

        self::assertSame(1, $this->korektury->prijmiVsechnyBezZmen(\Uzivatel::SYSTEM));
        self::assertSame(1, (int) dbOneCol('SELECT probehla_korekce FROM akce_seznam WHERE id_akce = $0', [$exportovana]));
    }

    /**
     * @test
     */
    public function prijetiNechaNezmenenyTextPresneJakBylIKoncumRadku(): void
    {
        $popisSWindowsKonciRadku = "První odstavec.\r\n\r\nDruhý odstavec.\r\n";
        $idAktivity = $this->vytvorAktivitu('Z Windows', popis: $popisSWindowsKonciRadku);
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-nazev>Z Windowsu</gc-nazev></gc-aktivita>");
        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->korektury->prijmi($korektura->id, $korektura->navrh(), \Uzivatel::SYSTEM);

        $radek = dbOneLine('SELECT nazev_akce, popis FROM akce_seznam WHERE id_akce = $0', [$idAktivity]);
        self::assertSame('Z Windowsu', $radek['nazev_akce']);
        self::assertSame($popisSWindowsKonciRadku, $radek['popis']);
    }

    /**
     * @test
     */
    public function zamitnutaAktivitaZustaneNeopravenaAJdeZnovuNaKorekturu(): void
    {
        $idAktivity = $this->vytvorAktivitu('Zamítnutá');
        $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $this->korektury->importuj("<gc-aktivita id=\"{$idAktivity}\"><gc-nazev>Úplně jiný název</gc-nazev></gc-aktivita>");
        $korektura = $this->repository->dejOtevrene([$idAktivity])[$idAktivity];

        $this->korektury->zamitni($korektura->id, \Uzivatel::SYSTEM);

        self::assertSame('Zamítnutá', dbOneCol('SELECT nazev_akce FROM akce_seznam WHERE id_akce = $0', [$idAktivity]));
        self::assertContains(
            $idAktivity,
            array_keys($this->repository->dejTextyKExportu($this->systemoveNastaveni->rocnik())),
        );
    }

    /**
     * @test
     */
    public function souborMaUAktivityDrivejsiOpravenouVerziSeStejnymNazvem(): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 2, probehlaKorekce: true, popis: 'Předloňský opravený popis.');
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 1, probehlaKorekce: true, popis: 'Loňský opravený popis.');
        $this->vytvorAktivitu('Opakovaná hra', rok: $rocnik - 1, probehlaKorekce: false, popis: 'Loňský neopravený popis.');
        $letosni = $this->vytvorAktivitu('Opakovaná hra');

        $baliky = $this->korektury->exportuj(1000, \Uzivatel::SYSTEM);
        $soubor = $this->korektury->soubor($baliky[0]);

        $blokAktivity = substr($soubor, strpos($soubor, '<gc-aktivita id="' . $letosni . '">'));
        $blokAktivity = substr($blokAktivity, 0, strpos($blokAktivity, '</gc-aktivita>'));
        self::assertStringContainsString('<gc-drivejsi rok="' . ($rocnik - 1) . '">', $blokAktivity);
        self::assertStringContainsString('Loňský opravený popis.', $blokAktivity);
        self::assertStringNotContainsString('neopravený', $blokAktivity);
    }

    private function vytvorAktivitu(
        string $nazev,
        ?int $rok = null,
        bool $probehlaKorekce = false,
        int $typ = TypAktivity::LARP,
        ?string $popis = null,
        ?int $patriPod = null,
    ): int {
        dbInsert('akce_seznam', [
            'nazev_akce'       => $nazev,
            'popis_kratky'     => "Krátký popis aktivity {$nazev}",
            'popis'            => $popis ?? "Popis aktivity {$nazev}.",
            'probehla_korekce' => (int) $probehlaKorekce,
            'typ'              => $typ,
            'stav'             => StavAktivity::NOVA,
            'rok'              => $rok ?? $this->systemoveNastaveni->rocnik(),
            'patri_pod'        => $patriPod,
        ]);

        return (int) dbInsertId();
    }

    /**
     * @return array{0: int, 1: int} the main activity and another instance
     */
    private function vytvorRodinuInstanci(string $nazev): array
    {
        $hlavni = $this->vytvorAktivitu($nazev);
        dbInsert('akce_instance', [
            'id_hlavni_akce' => $hlavni,
        ]);
        $idInstance = (int) dbInsertId();
        dbQuery('UPDATE akce_seznam SET patri_pod = $0 WHERE id_akce = $1', [$idInstance, $hlavni]);

        return [$hlavni, $this->vytvorAktivitu($nazev, patriPod: $idInstance)];
    }
}
