<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Report\FinanceReportNeplaticu;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\KategorieNeplatice;
use OpenSpout\Reader\XLSX\Reader;

class FinanceReportNeplaticuTest extends AbstractTestDb
{
    private const int ID_PREDMETU = 12800;
    // kategorie 2, přezdívka s HTML značkami
    private const int ID_DLUZNIKA = 12801;
    // kategorie 7, v reportu není
    private const int ID_NEDLUZNIKA = 12802;
    // kategorie 1 s největším dluhem, přihlašuje se e-mailem
    private const int ID_NEPOSLAL_NIC_VELKY_DLUH = 12803;
    // kategorie 1 s menším dluhem, přezdívka se znakem '<' a bez e-mailu
    private const int ID_NEPOSLAL_NIC_MENSI_DLUH = 12804;
    // kategorie 1 se stejným dluhem jako předchozí, rozhodne ID
    private const int ID_NEPOSLAL_NIC_MENSI_DLUH_VYSSI_ID = 12805;
    // kategorie 3
    private const int ID_MALY_DLUH = 12806;
    // kategorie 5
    private const int ID_REGISTROVAL_SE_PO_VLNE = 12807;
    // kategorie 6
    private const int ID_NEODHLASOVAT = 12808;
    // bez kategorie, letos přihlášený není
    private const int ID_NEPRIHLASENY = 12809;

    private const string PREZDIVKA_DLUZNIKA = '<b>dluznice</b>';
    private const string PREZDIVKA_SE_SRDICKEM = '<3 Lucka';

    // Data se počítají jednou, ať fixtury a očekávané hodnoty neleží každé na jiné straně půlnoci.
    private static \DateTimeImmutable $vlna;
    private static \DateTimeImmutable $registraceVcas;
    private static \DateTimeImmutable $posledniPlatbaDluznika;

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $rocnik = $systemoveNastaveni->rocnik();

        self::$vlna = \DateTimeImmutable::createFromInterface($systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy());
        // tak dlouho před vlnou, aby ho nechránila registrace pár dní před ní (kategorie 5)
        self::$registraceVcas = self::$vlna
            ->modify(sprintf('-%d days', $systemoveNastaveni->neplaticPocetDnuPredVlnouKdyJeChranen() + 20))
            ->setTime(9, 15);
        self::$posledniPlatbaDluznika = self::$vlna->modify('-3 days')->setTime(13, 45);

        $registraceVcas = self::$registraceVcas->format(DateTimeCz::FORMAT_DB);
        $registracePoVlne = self::$vlna->modify('+1 day')->format(DateTimeCz::FORMAT_DB);
        $predposledniPlatba = self::$vlna->modify('-5 days')->format(DateTimeCz::FORMAT_DB);
        $posledniPlatba = self::$posledniPlatbaDluznika->format(DateTimeCz::FORMAT_DB);
        // dluh pod hranicí velkého dluhu, aby to byla kategorie 3 a ne 2
        $cenaMalehoDluhu = 100 + $systemoveNastaveni->neplaticCastkaVelkyDluh() / 2;

        $idPredmetu = self::ID_PREDMETU;
        $vstupne = TypPredmetu::VSTUPNE;
        $kodPredmetu = 'report_neplaticu_' . $rocnik;
        $prihlasen = Role::PRIHLASEN_NA_LETOSNI_GC;
        $neodhlasovat = Role::LETOSNI_NEODHLASOVAT;
        $system = \Uzivatel::SYSTEM;
        $dluznik = self::ID_DLUZNIKA;
        $nedluznik = self::ID_NEDLUZNIKA;
        $velkyDluh = self::ID_NEPOSLAL_NIC_VELKY_DLUH;
        $mensiDluh = self::ID_NEPOSLAL_NIC_MENSI_DLUH;
        $mensiDluhVyssiId = self::ID_NEPOSLAL_NIC_MENSI_DLUH_VYSSI_ID;
        $malyDluh = self::ID_MALY_DLUH;
        $poVlne = self::ID_REGISTROVAL_SE_PO_VLNE;
        $neodhlasovany = self::ID_NEODHLASOVAT;
        $neprihlaseny = self::ID_NEPRIHLASENY;

        return [
            <<<SQL
            INSERT INTO shop_predmety
            SET id_predmetu = {$idPredmetu}, nazev = 'Report neplatičů', model_rok = {$rocnik},
                kod_predmetu = '{$kodPredmetu}', typ = {$vstupne}, cena_aktualni = 0.0
            SQL,
            self::uzivatelQuery(self::ID_DLUZNIKA, self::PREZDIVKA_DLUZNIKA, 'Dlužná', 'Nováková'),
            self::uzivatelQuery(self::ID_NEDLUZNIKA, 'nedluznik', 'Nedlužný', 'Svoboda'),
            self::uzivatelQuery(
                self::ID_NEPOSLAL_NIC_VELKY_DLUH,
                self::email(self::ID_NEPOSLAL_NIC_VELKY_DLUH),
                'Emil',
                'Loginem',
            ),
            self::uzivatelQuery(self::ID_NEPOSLAL_NIC_MENSI_DLUH, self::PREZDIVKA_SE_SRDICKEM, 'Lucie', 'Bezmailová', ''),
            self::uzivatelQuery(self::ID_NEPOSLAL_NIC_MENSI_DLUH_VYSSI_ID, 'stejny_dluh', 'Stejný', 'Dluh'),
            self::uzivatelQuery(self::ID_MALY_DLUH, 'maly_dluh', 'Malý', 'Dluh'),
            self::uzivatelQuery(self::ID_REGISTROVAL_SE_PO_VLNE, 'po_vlne', 'Pozdní', 'Registrace'),
            self::uzivatelQuery(self::ID_NEODHLASOVAT, 'neodhlasovat', 'Chráněný', 'Org'),
            self::uzivatelQuery(self::ID_NEPRIHLASENY, 'neprihlaseny', 'Letos', 'Nejede'),
            <<<SQL
            INSERT INTO uzivatele_role (id_uzivatele, id_role, posadil, posazen)
            VALUES ({$dluznik}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$nedluznik}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$velkyDluh}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$mensiDluh}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$mensiDluhVyssiId}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$malyDluh}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$poVlne}, {$prihlasen}, {$system}, '{$registracePoVlne}'),
                   ({$neodhlasovany}, {$prihlasen}, {$system}, '{$registraceVcas}'),
                   ({$neodhlasovany}, {$neodhlasovat}, {$system}, '{$registraceVcas}')
            SQL,
            <<<SQL
            INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, rok, cena_nakupni)
            VALUES ({$dluznik}, {$idPredmetu}, {$rocnik}, 5000),
                   ({$velkyDluh}, {$idPredmetu}, {$rocnik}, 5000),
                   ({$mensiDluh}, {$idPredmetu}, {$rocnik}, 3000),
                   ({$mensiDluhVyssiId}, {$idPredmetu}, {$rocnik}, 3000),
                   ({$malyDluh}, {$idPredmetu}, {$rocnik}, {$cenaMalehoDluhu}),
                   ({$poVlne}, {$idPredmetu}, {$rocnik}, 3000),
                   ({$neodhlasovany}, {$idPredmetu}, {$rocnik}, 3000),
                   ({$neprihlaseny}, {$idPredmetu}, {$rocnik}, 3000)
            SQL,
            <<<SQL
            INSERT INTO platby (id_uzivatele, rok, castka, provedl, provedeno)
            VALUES ({$dluznik}, {$rocnik}, 50, {$system}, '{$predposledniPlatba}'),
                   ({$dluznik}, {$rocnik}, 50, {$system}, '{$posledniPlatba}'),
                   ({$malyDluh}, {$rocnik}, 100, {$system}, '{$predposledniPlatba}'),
                   ({$nedluznik}, {$rocnik}, 500, {$system}, '{$predposledniPlatba}')
            SQL,
        ];
    }

    private static function uzivatelQuery(
        int $idUzivatele,
        string $login,
        string $jmeno,
        string $prijmeni,
        ?string $email = null,
    ): string {
        $email ??= self::email($idUzivatele);

        return <<<SQL
            INSERT INTO uzivatele_hodnoty
            SET id_uzivatele = {$idUzivatele}, login_uzivatele = '{$login}', jmeno_uzivatele = '{$jmeno}',
                prijmeni_uzivatele = '{$prijmeni}', email1_uzivatele = '{$email}',
                telefon_uzivatele = '+420 123 456 789'
            SQL;
    }

    private static function email(int $idUzivatele): string
    {
        return "report.neplaticu.{$idUzivatele}@example.com";
    }

    /**
     * @test
     */
    public function vynechaKohoNicNedluzi(): void
    {
        $radky = $this->report()->getRadky($this->getUcastnici(), 'xlsx');

        self::assertSame(
            KategorieNeplatice::NEDLUZNIK,
            \Uzivatel::zIdUrcite(self::ID_NEDLUZNIKA)->finance()->kategorieNeplatice()->ciselnaKategoriiNeplatice(),
            'Předpoklad testu: účastník s kladným zůstatkem je v kategorii 7',
        );
        self::assertNotContains(self::ID_NEDLUZNIKA, array_column($radky, 'id_uzivatele'));
        self::assertNotContains(KategorieNeplatice::NEDLUZNIK, array_column($radky, 'kategorie_neplatice'));
    }

    /**
     * @test
     */
    public function radiPodleKategorieZustatkuAId(): void
    {
        $radky = $this->report()->getRadky($this->getUcastnici(), 'xlsx');

        self::assertSame(
            [
                KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
                KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH,
                KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU,
                KategorieNeplatice::MA_PRAVO_NEODHLASOVAT,
                null,
            ],
            array_column($radky, 'kategorie_neplatice'),
            'Kategorie podle čísla, kdo kategorii nemá, je na konci',
        );
        self::assertSame(
            [
                self::ID_NEPOSLAL_NIC_VELKY_DLUH, // v kategorii nejdřív největší dluh
                self::ID_NEPOSLAL_NIC_MENSI_DLUH, // při stejném dluhu nižší ID
                self::ID_NEPOSLAL_NIC_MENSI_DLUH_VYSSI_ID,
                self::ID_DLUZNIKA,
                self::ID_MALY_DLUH,
                self::ID_REGISTROVAL_SE_PO_VLNE,
                self::ID_NEODHLASOVAT,
                self::ID_NEPRIHLASENY,
            ],
            array_column($radky, 'id_uzivatele'),
        );
    }

    /**
     * @test
     */
    public function maNickJmenoAPrijmeniZvlastABezTelefonu(): void
    {
        $report = $this->report();
        $radek = $this->getRadekUcastnika($report->getRadky($this->getUcastnici(), 'xlsx'), self::ID_DLUZNIKA);

        self::assertSame($report->getSloupce('xlsx'), array_keys($radek));
        self::assertNotContains('telefon', $report->getSloupce('xlsx'));
        self::assertSame(self::PREZDIVKA_DLUZNIKA, $radek['nick']);
        self::assertSame('Dlužná', $radek['jmeno_uzivatele']);
        self::assertSame('Nováková', $radek['prijmeni_uzivatele']);
        self::assertSame(URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . self::ID_DLUZNIKA, $radek['uzivatel_v_adminu']);
    }

    /**
     * @test
     */
    public function prihlasovaciEmailNeuvadiJakoNick(): void
    {
        $radek = $this->getRadekUcastnika(
            $this->report()->getRadky($this->getUcastnici(), 'xlsx'),
            self::ID_NEPOSLAL_NIC_VELKY_DLUH,
        );

        self::assertSame('', $radek['nick']);
        self::assertSame(self::email(self::ID_NEPOSLAL_NIC_VELKY_DLUH), $radek['email']);
    }

    /**
     * Bez času podle karty 1280. Vlna zůstává s časem, kategorie 5 se od ní počítá na sekundy.
     *
     * @test
     */
    public function datumPosledniPlatbyAPrihlaseniJsouBezCasuAVsechnaDataJsouIso(): void
    {
        $radek = $this->getRadekUcastnika($this->report()->getRadky($this->getUcastnici(), 'xlsx'), self::ID_DLUZNIKA);

        self::assertSame(self::$posledniPlatbaDluznika->format('Y-m-d'), $radek['datum_posledni_platby']);
        self::assertSame(self::$registraceVcas->format('Y-m-d'), $radek['prihlaseni_na_letosni_gc']);
        self::assertSame(self::$vlna->format('Y-m-d H:i:s'), $radek['hromadne_odhlaseni']);
    }

    /**
     * @test
     */
    public function vHtmlVedeOdkazDoAdminuZIdAPrazdnyEmailNeniOdkaz(): void
    {
        $report = $this->report();
        $radky = $report->getRadky($this->getUcastnici(), 'html');
        $dluznik = $this->getRadekUcastnika($radky, self::ID_DLUZNIKA);

        self::assertSame($report->getSloupce('html'), array_keys($dluznik));
        self::assertNotContains('uzivatel_v_adminu', $report->getSloupce('html'));
        self::assertSame(
            "<a target='_blank' href='" . URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . self::ID_DLUZNIKA . "'>"
            . self::ID_DLUZNIKA . '</a>',
            $dluznik['id_uzivatele'],
        );
        self::assertSame('&lt;b&gt;dluznice&lt;/b&gt;', $dluznik['nick']);
        self::assertSame('', $this->getRadekUcastnika($radky, self::ID_NEPOSLAL_NIC_MENSI_DLUH)['email']);
    }

    /**
     * Report::tFormat() formát ořezává, takže report musí escapovat i pro „html“ s bílými znaky okolo.
     *
     * @test
     *
     * @dataProvider provideFormatHtml
     */
    public function exportDoHtmlEscapujeUdajeUcastniku(string $format): void
    {
        $html = $this->getVystupExportu($format);
        $email = self::email(self::ID_DLUZNIKA);

        self::assertStringContainsString('<td>&lt;b&gt;dluznice&lt;/b&gt;</td>', $html);
        self::assertStringNotContainsString(self::PREZDIVKA_DLUZNIKA, $html);
        self::assertStringContainsString('<td>&lt;3 Lucka</td>', $html);
        self::assertStringNotContainsString('<th>uzivatel_v_adminu</th>', $html);
        self::assertStringContainsString(
            "<a target='_blank' href='" . htmlspecialchars(URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . self::ID_DLUZNIKA)
            . "'>" . self::ID_DLUZNIKA . '</a>',
            $html,
        );
        self::assertStringContainsString("<a href='mailto:{$email}'>{$email}</a>", $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideFormatHtml(): array
    {
        return [
            'html'                => ['html'],
            'html s mezerou'      => [' html'],
            'html s koncem řádku' => ["html\n"],
        ];
    }

    /**
     * @test
     */
    public function exportDoCsvMaHodnotyPodSvymiHlavickamiANechavaZnakyVPrezdivce(): void
    {
        $radky = array_map(
            static fn (string $radek): array => str_getcsv($radek, ';', '"', '\\'),
            explode("\n", trim(str_replace("\u{FEFF}", '', $this->getVystupExportu('csv')))),
        );
        $hlavicka = array_shift($radky);

        self::assertSame($this->report()->getSloupce('csv'), $hlavicka);
        $this->assertHodnotyPodSvymiHlavickami($this->getRadkyPodleId($hlavicka, $radky));
    }

    /**
     * @test
     */
    public function exportDoXlsxMaHodnotyPodSvymiHlavickami(): void
    {
        $soubor = sys_get_temp_dir() . '/' . uniqid('report_neplaticu_', true) . '.xlsx';
        file_put_contents($soubor, $this->getVystupExportu('xlsx'));
        $reader = new Reader();
        $reader->open($soubor);
        $radky = [];
        foreach ($reader->getSheetIterator() as $list) {
            foreach ($list->getRowIterator() as $radek) {
                $radky[] = array_map(static fn ($hodnota): string => (string) $hodnota, $radek->toArray());
            }
            break;
        }
        $reader->close();
        unlink($soubor);
        $hlavicka = array_shift($radky);

        self::assertSame($this->report()->getSloupce('xlsx'), $hlavicka);
        $this->assertHodnotyPodSvymiHlavickami($this->getRadkyPodleId($hlavicka, $radky));
    }

    /**
     * @param array<int, array<string, string>> $radkyPodleId
     */
    private function assertHodnotyPodSvymiHlavickami(array $radkyPodleId): void
    {
        $dluznik = $radkyPodleId[self::ID_DLUZNIKA];
        self::assertSame(self::PREZDIVKA_DLUZNIKA, $dluznik['nick']);
        self::assertSame('Nováková', $dluznik['prijmeni_uzivatele']);
        self::assertSame(URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . self::ID_DLUZNIKA, $dluznik['uzivatel_v_adminu']);
        self::assertSame(self::email(self::ID_DLUZNIKA), $dluznik['email']);
        self::assertSame((string) KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH, $dluznik['kategorie_neplatice']);
        self::assertSame('-4900', $dluznik['aktualni_zustatek']);
        self::assertSame(self::$vlna->format('Y-m-d H:i:s'), $dluznik['hromadne_odhlaseni']);

        $bezEmailu = $radkyPodleId[self::ID_NEPOSLAL_NIC_MENSI_DLUH];
        self::assertSame(self::PREZDIVKA_SE_SRDICKEM, $bezEmailu['nick']);
        self::assertSame('', $bezEmailu['email']);
        self::assertSame('-3000', $bezEmailu['aktualni_zustatek']);
    }

    /**
     * @param list<string>       $hlavicka
     * @param list<list<string>> $radky
     *
     * @return array<int, array<string, string>>
     */
    private function getRadkyPodleId(array $hlavicka, array $radky): array
    {
        $podleId = [];
        foreach ($radky as $radek) {
            // XLSX vynechává prázdné buňky na konci řádku
            $radek = array_combine($hlavicka, array_pad($radek, count($hlavicka), ''));
            $podleId[(int) $radek['id_uzivatele']] = $radek;
        }

        return $podleId;
    }

    /**
     * Výstup se sbírá přes buffer, protože report píše rovnou na výstup.
     */
    private function getVystupExportu(string $format): string
    {
        // Název staženého souboru se odvozuje z REQUEST_URI, které v CLI neexistuje. Ponechané
        // by shodilo pozdější testy, viz ReportCsvNullTest::csv().
        $puvodniRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/admin/reporty/finance-report-neplaticu?format=' . rawurlencode($format);

        ob_start();
        try {
            $this->report()->exportuj($format);
        } finally {
            $vystup = (string) ob_get_clean();
            if ($puvodniRequestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $puvodniRequestUri;
            }
        }

        return $vystup;
    }

    private function report(): FinanceReportNeplaticu
    {
        return new FinanceReportNeplaticu(SystemoveNastaveni::zGlobals());
    }

    /**
     * @param list<array<string, int|float|string|null>> $radky
     *
     * @return array<string, int|float|string|null>
     */
    private function getRadekUcastnika(array $radky, int $idUzivatele): array
    {
        foreach ($radky as $radek) {
            if ($radek['id_uzivatele'] === $idUzivatele
                || str_contains((string) $radek['id_uzivatele'], ">{$idUzivatele}</a>")
            ) {
                return $radek;
            }
        }

        self::fail("Účastník {$idUzivatele} v reportu není");
    }

    /**
     * Schválně jinak, než je report seřadí.
     *
     * @return list<\Uzivatel>
     */
    private function getUcastnici(): array
    {
        return array_map(
            static fn (int $idUzivatele): \Uzivatel => \Uzivatel::zIdUrcite($idUzivatele),
            [
                self::ID_NEPRIHLASENY,
                self::ID_NEODHLASOVAT,
                self::ID_NEPOSLAL_NIC_MENSI_DLUH_VYSSI_ID,
                self::ID_NEDLUZNIKA,
                self::ID_REGISTROVAL_SE_PO_VLNE,
                self::ID_MALY_DLUH,
                self::ID_DLUZNIKA,
                self::ID_NEPOSLAL_NIC_MENSI_DLUH,
                self::ID_NEPOSLAL_NIC_VELKY_DLUH,
            ],
        );
    }
}
