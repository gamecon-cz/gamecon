<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class AktivitaCenaBrigadnickeAktivityTest extends AbstractTestDb
{
    private const int ID_AKTIVITY = 7201;
    private const int CENA_Z_FORMULARE = 999;
    private const int ID_UZIVATELE = 7301;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;
        $typBrigadnicka = TypAktivity::BRIGADNICKA;

        return [
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (7301, 'test-admin-editoru', 'Test', 'Admin', 'test-admin-editoru@example.invalid')
SQL,
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
        unset($_SESSION[\Uzivatel::UZIVATEL]);
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

    public function testEditorCenaJsonVratiCenuBrigadnickeAktivity(): void
    {
        $this->nastavFormular(TypAktivity::BRIGADNICKA, '8', '12', 150);

        self::assertSame([
            'cena' => 600,
        ], $this->zavolejEditorCenaJson());
    }

    /**
     * @dataProvider provideFormularyBezSpocitatelneCeny
     */
    public function testEditorCenaJsonVratiNullKdyzCenuNejdeSpocitat(
        int $typ,
        string $zacatek,
        string $konec,
        int $odmenaZaHodinu,
    ): void {
        $this->nastavFormular($typ, $zacatek, $konec, $odmenaZaHodinu);

        self::assertSame([
            'cena' => null,
        ], $this->zavolejEditorCenaJson());
    }

    public static function provideFormularyBezSpocitatelneCeny(): array
    {
        return [
            'jiný typ aktivity'    => [TypAktivity::DESKOHERNA, '8', '12', 150],
            'bez odměny za hodinu' => [TypAktivity::BRIGADNICKA, '8', '12', 0],
            'bez času'             => [TypAktivity::BRIGADNICKA, '', '', 150],
            'bez konce'            => [TypAktivity::BRIGADNICKA, '8', '', 150],
            'konec před začátkem'  => [TypAktivity::BRIGADNICKA, '14', '10', 150],
        ];
    }

    public function testKonecPredZacatkemZustaneCenaZFormulare(): void
    {
        self::assertSame(self::CENA_Z_FORMULARE, $this->ulozPresEditor(TypAktivity::BRIGADNICKA, '14', '10', 150));
    }

    public function testEditorPosilaAjaxuKlicProCenu(): void
    {
        // Without it XTemplate compiles the template next to its source, into the tracked tree
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
        $_SESSION[\Uzivatel::UZIVATEL] = [
            'id_uzivatele' => self::ID_UZIVATELE,
        ];

        $html = Aktivita::editor(SystemoveNastaveni::zGlobals(), null);

        self::assertStringContainsString('&' . Aktivita::AJAX_CENA_KLIC . '=true', $html);
        self::assertStringContainsString('id="odmenaZaHodinuCelkem"', $html);
        self::assertStringContainsString("=== '" . TypAktivity::BRIGADNICKA . "'", $html);
    }

    /**
     * @dataProvider provideDataBezSpocitatelneCeny
     */
    public function testVzorecVratiNullKdyzCenuNejdeSpocitat(array $data, int $odmenaZaHodinu): void
    {
        self::assertNull(Aktivita::vypocitejCenuBrigadnickeAktivity($data, $odmenaZaHodinu));
    }

    public static function provideDataBezSpocitatelneCeny(): array
    {
        $brigadnicka = [
            Sql::TYP     => TypAktivity::BRIGADNICKA,
            Sql::ZACATEK => '2026-07-17 10:00:00',
            Sql::KONEC   => '2026-07-17 13:00:00',
        ];

        return [
            'bez typu' => [array_diff_key($brigadnicka, [
                Sql::TYP => true,
            ]), 100],
            'jiný typ' => [[
                Sql::TYP => TypAktivity::LARP,
            ] + $brigadnicka, 100],
            'bez odměny'  => [$brigadnicka, 0],
            'bez začátku' => [array_diff_key($brigadnicka, [
                Sql::ZACATEK => true,
            ]), 100],
            'bez konce' => [array_diff_key($brigadnicka, [
                Sql::KONEC => true,
            ]), 100],
            'konec shodný se začátkem' => [[
                Sql::KONEC => '2026-07-17 10:00:00',
            ] + $brigadnicka, 100],
            'konec před začátkem' => [[
                Sql::KONEC => '2026-07-17 09:00:00',
            ] + $brigadnicka, 100],
            'méně než půl hodiny' => [[
                Sql::KONEC => '2026-07-17 10:20:00',
            ] + $brigadnicka, 100],
        ];
    }

    public function testVzorecVratiOdmenuKratCeleHodiny(): void
    {
        self::assertSame(
            300,
            Aktivita::vypocitejCenuBrigadnickeAktivity(
                [
                    Sql::TYP     => TypAktivity::BRIGADNICKA,
                    Sql::ZACATEK => '2026-07-17 10:00:00',
                    Sql::KONEC   => '2026-07-17 13:00:00',
                ],
                100,
            ),
        );
    }

    public function testEditorCenaTestJsonPoznaDotazNaCenu(): void
    {
        self::assertFalse(Aktivita::editorCenaTestJson());

        $_POST[Aktivita::AJAX_CENA_KLIC] = 'true';

        self::assertTrue(Aktivita::editorCenaTestJson());
    }

    /**
     * @dataProvider provideCasyAOdmeny
     */
    public function testEditorCenaJsonVratiStejnouCenuJakoUlozeniFormulare(
        string $zacatek,
        string $konec,
        int $odmenaZaHodinu,
    ): void {
        $this->nastavFormular(TypAktivity::BRIGADNICKA, $zacatek, $konec, $odmenaZaHodinu);
        $cenaZAjaxu = $this->zavolejEditorCenaJson()['cena'];

        self::assertNotNull(Aktivita::editorZpracuj(null), 'Aktivita se má uložit.');

        self::assertSame($this->ulozenaCena(), $cenaZAjaxu);
    }

    public static function provideCasyAOdmeny(): array
    {
        return [
            'dopoledne'                            => ['8', '12', 150],
            'přes půlnoc'                          => ['22', '2', 150],
            'celý den'                             => ['9', '18', 130],
            'jedna hodina'                         => ['13', '14', 175],
            'stejná hodina je celý programový den' => ['8', '8', 150],
        ];
    }

    private function ulozPresEditor(int $typ, string $zacatek, string $konec, int $odmenaZaHodinu): int
    {
        $this->nastavFormular($typ, $zacatek, $konec, $odmenaZaHodinu);

        self::assertNotNull(Aktivita::editorZpracuj(null), 'Aktivita se má uložit.');

        return $this->ulozenaCena();
    }

    /**
     * @return array{cena: int|null}
     */
    private function zavolejEditorCenaJson(): array
    {
        return json_decode(Aktivita::editorCenaJson(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function nastavFormular(int $typ, string $zacatek, string $konec, int $odmenaZaHodinu): void
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
    }

    private function ulozenaCena(): int
    {
        return (int) dbOneCol('SELECT cena FROM akce_seznam WHERE id_akce = $0', [
            0 => self::ID_AKTIVITY,
        ]);
    }
}
