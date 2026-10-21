<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class AktivitaCenaBrigadnickeAktivityTest extends AbstractTestDb
{
    private const ID_AKTIVITY = 7201;
    private const CENA_Z_FORMULARE = 999;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;
        $typBrigadnicka = TypAktivity::BRIGADNICKA;

        return [
            <<<SQL
INSERT INTO akce_seznam(
    id_akce, nazev_akce, url_akce, typ, rok, stav, patri_pod, zacatek, konec, cena, teamova, team_min, team_max, kapacita, kapacita_f, kapacita_m
)
VALUES
    (7201, 'Brigádnická aktivita', 'brigadnicka-aktivita-cena-test', {$typBrigadnicka}, {$rok}, 2, NULL, '{$rok}-07-17 08:00:00', '{$rok}-07-17 12:00:00', 600, 0, NULL, NULL, 5, 0, 0)
SQL,
        ];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        \Chyba::vyzvedniHtml();

        parent::tearDown();
    }

    public function testCenaJeOdmenaZaHodinuKratPocetHodin(): void
    {
        self::assertSame(600, $this->ulozPresEditor(TypAktivity::BRIGADNICKA, '8', '12', 150));
    }

    public function testPocetHodinSePocitaIPresPulnoc(): void
    {
        self::assertSame(600, $this->ulozPresEditor(TypAktivity::BRIGADNICKA, '22', '2', 150));
    }

    public function testBezOdmenyZustaneCenaZFormulare(): void
    {
        self::assertSame(self::CENA_Z_FORMULARE, $this->ulozPresEditor(TypAktivity::BRIGADNICKA, '8', '12', 0));
    }

    public function testOdmenaSeIgnorujeUAktivityJineNezBrigadnicke(): void
    {
        self::assertSame(self::CENA_Z_FORMULARE, $this->ulozPresEditor(TypAktivity::DESKOHERNA, '8', '12', 150));
    }

    public function testBezCasuZustaneCenaZFormulare(): void
    {
        self::assertSame(self::CENA_Z_FORMULARE, $this->ulozPresEditor(TypAktivity::BRIGADNICKA, '', '', 150));
    }

    /**
     * @dataProvider provideNeceleHodiny
     */
    public function testPocetHodinSeZaokrouhlujeNaCele(string $zacatek, string $konec, int $ocekavanaCena): void
    {
        $datum = ROCNIK . '-07-17';
        Aktivita::uloz(
            data: [
                Sql::ID_AKCE    => self::ID_AKTIVITY,
                Sql::NAZEV_AKCE => 'Brigádnická aktivita',
                Sql::URL_AKCE   => 'brigadnicka-aktivita-cena-test',
                Sql::ROK        => ROCNIK,
                Sql::STAV       => StavAktivity::AKTIVOVANA,
                Sql::TYP        => TypAktivity::BRIGADNICKA,
                Sql::ZACATEK    => "{$datum} {$zacatek}",
                Sql::KONEC      => "{$datum} {$konec}",
                Sql::KAPACITA   => 5,
                Sql::KAPACITA_F => 0,
                Sql::KAPACITA_M => 0,
                Sql::CENA       => self::CENA_Z_FORMULARE,
                Sql::TEAMOVA    => 0,
            ],
            markdownPopis: null,
            organizatoriIds: [],
            lokaceIds: [],
            hlavniLokaceId: null,
            tagIds: [],
            odmenaZaHodinu: 100,
            systemoveNastaveni: SystemoveNastaveni::zGlobals(),
        );

        self::assertSame($ocekavanaCena, $this->ulozenaCena());
    }

    public static function provideNeceleHodiny(): array
    {
        return [
            'hodina a půl se zaokrouhlí nahoru' => ['10:00:00', '11:30:00', 200],
            'půl hodiny se zaokrouhlí nahoru'   => ['10:00:00', '10:30:00', 100],
            'čtvrt hodiny cenu nezmění'         => ['10:00:00', '10:15:00', self::CENA_Z_FORMULARE],
        ];
    }

    private function ulozPresEditor(int $typ, string $zacatek, string $konec, int $odmenaZaHodinu): int
    {
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => self::ID_AKTIVITY,
            Sql::NAZEV_AKCE => 'Brigádnická aktivita',
            Sql::URL_AKCE   => 'brigadnicka-aktivita-cena-test',
            Sql::PATRI_POD  => '',
            Sql::POPIS      => '',
            'den'           => ROCNIK . '-07-17',
            Sql::ZACATEK    => $zacatek,
            Sql::KONEC      => $konec,
            Sql::TYP        => $typ,
            Sql::CENA       => self::CENA_Z_FORMULARE,
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => 5,
            Sql::KAPACITA_F => 0,
            Sql::KAPACITA_M => 0,
        ];
        $_POST[Aktivita::ODMENA_ZA_HODINU_KLIC] = (string) $odmenaZaHodinu;

        self::assertNotNull(Aktivita::editorZpracuj(null), 'Aktivita se má uložit.');

        return $this->ulozenaCena();
    }

    private function ulozenaCena(): int
    {
        return (int) dbOneCol('SELECT cena FROM akce_seznam WHERE id_akce = $0', [
            0 => self::ID_AKTIVITY,
        ]);
    }
}
