<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Logger\JobResultLoggerInterface;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Uzivatel\UzivatelKPromlceni;
use Uzivatel;

/**
 * Database integration tests for PromlceniZustatku
 *
 * Tests the business logic for automatic expiration of user balances.
 */
class PromlceniZustatkuTest extends AbstractTestDb
{
    // Test user IDs
    private const ID_KLADNY_ZUSTATEK_STARA_UCAST = 1001;
    private const ID_KLADNY_ZUSTATEK_NEDAVNA_UCAST = 1002;
    private const ID_NULOVY_ZUSTATEK = 1003;
    private const ID_ZAPORNY_ZUSTATEK = 1004;
    private const ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST = 1005;
    private const ID_KLADNY_ZUSTATEK_STARA_UCAST_2 = 1006;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $queries = [];
        $rocnik = ROCNIK;
        $staryRocnik = $rocnik - PromlceniZustatku::getPocetLetNeplatnosti() - 1; // Více než 3 roky zpět
        $nedavnyRocnik = $rocnik - 1; // Pouze 1 rok zpět

        // Vytvoříme role účasti pro historické ročníky (pokud neexistují)
        $queries[] = self::roleUcastiQuery($staryRocnik);
        $queries[] = self::roleUcastiQuery($nedavnyRocnik);

        // Uživatel s kladným zůstatkem a starou účastí - měl by být nalezen k promlčení
        $queries[] = self::uzivatelQuery(
            self::ID_KLADNY_ZUSTATEK_STARA_UCAST,
            'Starý',
            'Účastník',
            500.0,
        );
        $queries[] = self::prihlasenNaRocnikQuery(self::ID_KLADNY_ZUSTATEK_STARA_UCAST, $staryRocnik);
        $queries[] = self::platbaQuery(self::ID_KLADNY_ZUSTATEK_STARA_UCAST, 500.0, $staryRocnik);

        // Uživatel s kladným zůstatkem a nedávnou účastí - NESMÍ být nalezen k promlčení
        $queries[] = self::uzivatelQuery(
            self::ID_KLADNY_ZUSTATEK_NEDAVNA_UCAST,
            'Nedávný',
            'Účastník',
            300.0,
        );
        $queries[] = self::prihlasenNaRocnikQuery(self::ID_KLADNY_ZUSTATEK_NEDAVNA_UCAST, $nedavnyRocnik);
        $queries[] = self::platbaQuery(self::ID_KLADNY_ZUSTATEK_NEDAVNA_UCAST, 300.0, $nedavnyRocnik);

        // Uživatel s nulovým zůstatkem - NESMÍ být nalezen k promlčení
        $queries[] = self::uzivatelQuery(
            self::ID_NULOVY_ZUSTATEK,
            'Nula',
            'Zůstatek',
            0.0,
        );
        $queries[] = self::prihlasenNaRocnikQuery(self::ID_NULOVY_ZUSTATEK, $staryRocnik);

        // Uživatel se záporným zůstatkem - NESMÍ být nalezen k promlčení
        $queries[] = self::uzivatelQuery(
            self::ID_ZAPORNY_ZUSTATEK,
            'Záporný',
            'Zůstatek',
            -100.0,
        );
        $queries[] = self::prihlasenNaRocnikQuery(self::ID_ZAPORNY_ZUSTATEK, $staryRocnik);

        // Uživatel s kladným zůstatkem bez účasti - měl by být nalezen k promlčení
        $queries[] = self::uzivatelQuery(
            self::ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST,
            'Nikdy',
            'Neúčastnil',
            250.0,
        );
        $queries[] = self::platbaQuery(self::ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST, 250.0, $rocnik - 2);

        // Druhý uživatel k promlčení pro testování více uživatelů najednou
        $queries[] = self::uzivatelQuery(
            self::ID_KLADNY_ZUSTATEK_STARA_UCAST_2,
            'Starý',
            'Účastník Dva',
            100.0,
        );
        $queries[] = self::prihlasenNaRocnikQuery(self::ID_KLADNY_ZUSTATEK_STARA_UCAST_2, $staryRocnik);
        $queries[] = self::platbaQuery(self::ID_KLADNY_ZUSTATEK_STARA_UCAST_2, 100.0, $staryRocnik, 3, 15);

        return $queries;
    }

    private static function uzivatelQuery(
        int $idUzivatele,
        string $jmeno,
        string $prijmeni,
        float $zustatek,
    ): string {
        $login = strtolower(str_replace(' ', '_', $jmeno . '_' . $prijmeni));
        $email = $login . '@test.cz';

        return <<<SQL
INSERT INTO uzivatele_hodnoty
SET id_uzivatele = {$idUzivatele},
    login_uzivatele = '{$login}',
    jmeno_uzivatele = '{$jmeno}',
    prijmeni_uzivatele = '{$prijmeni}',
    email1_uzivatele = '{$email}',
    zustatek = {$zustatek}
SQL;
    }

    private static function roleUcastiQuery(int $rocnik): string
    {
        $idRole = Role::prihlasenNaRocnik($rocnik);
        $kodRole = "GC{$rocnik}_PRIHLASEN";
        $nazevRole = "Přihlášen {$rocnik}";
        $typRole = Role::TYP_UCAST;
        $vyznam = Role::VYZNAM_PRIHLASEN;

        return <<<SQL
INSERT IGNORE INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ({$idRole}, '{$kodRole}', '{$nazevRole}', '{$nazevRole}', {$rocnik}, '{$typRole}', '{$vyznam}')
SQL;
    }

    private static function prihlasenNaRocnikQuery(int $idUzivatele, int $rocnik): string
    {
        $idRole = Role::prihlasenNaRocnik($rocnik);

        return <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
VALUES ({$idUzivatele}, {$idRole}, 1)
SQL;
    }

    private static function platbaQuery(
        int $idUzivatele,
        float $castka,
        int $rok,
        int $mesic = 6,
        int $den = 15,
    ): string {
        $datum = sprintf('%04d-%02d-%02d 12:00:00', $rok, $mesic, $den);

        return <<<SQL
INSERT INTO platby(id_uzivatele, castka, rok, provedl, provedeno)
VALUES ({$idUzivatele}, {$castka}, {$rok}, 1, '{$datum}')
SQL;
    }

    // ==================== Unit tests (mocks only) ====================

    /**
     * @test
     */
    public function pocetLetNeplatnostiJeDefinovan()
    {
        self::assertSame(3, PromlceniZustatku::getPocetLetNeplatnosti());
    }

    /**
     * @test
     */
    public function vytvorimCFOReportSPrazdnymSeznamemUzivatelu()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('jePoPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLoggerInterface::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);
        $report = $promlceniZustatku->vytvorCfoReport([]);

        self::assertSame([], $report);
    }

    /**
     * @test
     */
    public function vytvorimCFOReportSJednimUzivatelem()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('jePoPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLoggerInterface::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);

        // Mock Uzivatel
        $uzivatel = $this->createMock(\Uzivatel::class);
        $uzivatel->method('id')->willReturn(123);
        $uzivatel->method('krestniJmeno')->willReturn('Jan');
        $uzivatel->method('prijmeni')->willReturn('Novák');
        $uzivatel->method('login')->willReturn('jan123');
        $uzivatel->method('mail')->willReturn('jan@example.com');

        $finance = $this->createMock(Finance::class);
        $finance->method('stav')->willReturn(500.0);
        $uzivatel->method('finance')->willReturn($finance);

        $uzivatele = [
            new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: '2020;2021',
                rokPosledniPlatby: 2021,
                mesicPosledniPlatby: 6,
                denPosledniPlatby: 15,
            ),
        ];

        $report = $promlceniZustatku->vytvorCfoReport($uzivatele);

        self::assertCount(1, $report);
        self::assertSame(123, $report[0]['id_uzivatele']);
        self::assertSame('Jan', $report[0]['jmeno']);
        self::assertSame('Novák', $report[0]['prijmeni']);
        self::assertSame('jan@example.com', $report[0]['email']);
        self::assertSame(500.0, $report[0]['promlcena_castka']);
        self::assertSame(2021, $report[0]['rok_posledni_platby']);
        self::assertSame(6, $report[0]['mesic_posledni_platby']);
        self::assertSame(15, $report[0]['den_posledni_platby']);
    }

    /**
     * @test
     */
    public function vytvorimCFOReportSUzivatelemBezUcasti()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('jePoPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLoggerInterface::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);

        // Mock Uzivatel
        $uzivatel = $this->createMock(\Uzivatel::class);
        $uzivatel->method('id')->willReturn(456);
        $uzivatel->method('login')->willReturn('petra456');
        $uzivatel->method('krestniJmeno')->willReturn('Petra');
        $uzivatel->method('mail')->willReturn('petra@example.com');

        $finance = $this->createMock(Finance::class);
        $finance->method('stav')->willReturn(1000.0);
        $uzivatel->method('finance')->willReturn($finance);

        $uzivatele = [
            new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: '', // Žádná účast
                rokPosledniPlatby: null,
                mesicPosledniPlatby: null,
                denPosledniPlatby: null,
            ),
        ];

        $report = $promlceniZustatku->vytvorCfoReport($uzivatele);

        self::assertCount(1, $report);
        self::assertSame(456, $report[0]['id_uzivatele']);
        self::assertSame('Petra', $report[0]['jmeno']);
        self::assertSame(1000.0, $report[0]['promlcena_castka']);
        self::assertNull($report[0]['rok_posledni_platby']);
        self::assertNull($report[0]['mesic_posledni_platby']);
        self::assertNull($report[0]['den_posledni_platby']);

        // Zkontroluj, že všechny roky účasti jsou "ne"
        for ($rok = 2009; $rok <= 2024; ++$rok) {
            $klic = 'účast ' . $rok;
            if (isset($report[0][$klic])) {
                self::assertSame('ne', $report[0][$klic], "Očekáváno 'ne' pro {$klic}");
            }
        }
    }

    // ==================== Database integration tests ====================

    /**
     * @test
     */
    public function najdeUzivateleSKladnymZustatkemAStarouUcastiKPromlceni()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $idsUzivatelu = array_map(
            fn (UzivatelKPromlceni $u) => $u->uzivatel->id(),
            $uzivatele,
        );

        self::assertContains(
            self::ID_KLADNY_ZUSTATEK_STARA_UCAST,
            $idsUzivatelu,
            'Uživatel s kladným zůstatkem a starou účastí by měl být nalezen k promlčení',
        );
    }

    /**
     * @test
     */
    public function najdeUzivateleSKladnymZustatkemBezUcastiKPromlceni()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $idsUzivatelu = array_map(
            fn (UzivatelKPromlceni $u) => $u->uzivatel->id(),
            $uzivatele,
        );

        self::assertContains(
            self::ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST,
            $idsUzivatelu,
            'Uživatel s kladným zůstatkem bez účasti by měl být nalezen k promlčení',
        );
    }

    /**
     * @test
     */
    public function nenajdeUzivateleSNedavnouUcasti()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $idsUzivatelu = array_map(
            fn (UzivatelKPromlceni $u) => $u->uzivatel->id(),
            $uzivatele,
        );

        self::assertNotContains(
            self::ID_KLADNY_ZUSTATEK_NEDAVNA_UCAST,
            $idsUzivatelu,
            'Uživatel s nedávnou účastí NESMÍ být nalezen k promlčení',
        );
    }

    /**
     * @test
     */
    public function nenajdeUzivateleSNulovymZustatkem()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $idsUzivatelu = array_map(
            fn (UzivatelKPromlceni $u) => $u->uzivatel->id(),
            $uzivatele,
        );

        self::assertNotContains(
            self::ID_NULOVY_ZUSTATEK,
            $idsUzivatelu,
            'Uživatel s nulovým zůstatkem NESMÍ být nalezen k promlčení',
        );
    }

    /**
     * @test
     */
    public function nenajdeUzivateleSeZapornymZustatkem()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $idsUzivatelu = array_map(
            fn (UzivatelKPromlceni $u) => $u->uzivatel->id(),
            $uzivatele,
        );

        self::assertNotContains(
            self::ID_ZAPORNY_ZUSTATEK,
            $idsUzivatelu,
            'Uživatel se záporným zůstatkem NESMÍ být nalezen k promlčení',
        );
    }

    /**
     * @test
     */
    public function uzivatelKPromlceniObsahujeSpravnaMetadata()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();

        $uzivatelDto = null;
        foreach ($uzivatele as $u) {
            if ($u->uzivatel->id() === self::ID_KLADNY_ZUSTATEK_STARA_UCAST_2) {
                $uzivatelDto = $u;
                break;
            }
        }

        self::assertNotNull($uzivatelDto, 'Testovací uživatel nebyl nalezen');
        self::assertInstanceOf(UzivatelKPromlceni::class, $uzivatelDto);

        // Zkontrolujeme metadata platby
        $staryRocnik = ROCNIK - PromlceniZustatku::getPocetLetNeplatnosti() - 1;
        self::assertSame($staryRocnik, $uzivatelDto->rokPosledniPlatby);
        self::assertSame(3, $uzivatelDto->mesicPosledniPlatby);
        self::assertSame(15, $uzivatelDto->denPosledniPlatby);

        // Zkontrolujeme informaci o účasti
        self::assertStringContainsString((string) $staryRocnik, $uzivatelDto->prihlaseniNaRocniky);
    }

    /**
     * @test
     */
    public function promlciZustatkyAVratiSpravnyPocetASumu()
    {
        // Ujistíme se, že adresář pro logy existuje
        $this->zajistiLogyAdresar();

        $promlceniZustatku = $this->dejPromlceniZustatku();

        // Před promlčením zkontrolujeme zůstatek
        $uzivatel = \Uzivatel::zId(self::ID_KLADNY_ZUSTATEK_STARA_UCAST);
        self::assertNotNull($uzivatel);

        $puvodniZustatek = dbFetchSingle(
            'SELECT zustatek FROM uzivatele_hodnoty WHERE id_uzivatele = $0',
            [self::ID_KLADNY_ZUSTATEK_STARA_UCAST],
        );
        self::assertSame('500', $puvodniZustatek);

        $vysledek = $promlceniZustatku->promlcZustatky(
            [self::ID_KLADNY_ZUSTATEK_STARA_UCAST],
            \Uzivatel::SYSTEM,
        );

        self::assertSame(1, $vysledek['pocet']);
        self::assertEquals(500.0, (float) $vysledek['suma']);

        // Zkontrolujeme, že zůstatek byl nastaven na 0
        $novyZustatek = dbFetchSingle(
            'SELECT zustatek FROM uzivatele_hodnoty WHERE id_uzivatele = $0',
            [self::ID_KLADNY_ZUSTATEK_STARA_UCAST],
        );
        self::assertSame('0', $novyZustatek);
    }

    /**
     * @test
     */
    public function promlciZustatkyZapiseLogSoubor()
    {
        // Ujistíme se, že adresář pro logy existuje
        $this->zajistiLogyAdresar();

        $promlceniZustatku = $this->dejPromlceniZustatku();

        // Odstraníme existující logy z dnešního dne, abychom mohli testovat čistě
        $dnesniLogy = glob(LOGY . '/promlceni-' . date('Y-m-d') . '*.log') ?: [];
        foreach ($dnesniLogy as $log) {
            @unlink($log);
        }

        $promlceniZustatku->promlcZustatky(
            [self::ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST],
            \Uzivatel::SYSTEM,
        );

        // Najdeme dnešní log soubor
        $dnesniLogy = glob(LOGY . '/promlceni-' . date('Y-m-d') . '*.log') ?: [];

        self::assertNotEmpty($dnesniLogy, 'Měl by být vytvořen log soubor');

        // Zkontrolujeme obsah nejnovějšího logu
        $nejnovejsiLog = end($dnesniLogy);
        $obsahLogu = file_get_contents($nejnovejsiLog);

        self::assertStringContainsString((string) self::ID_KLADNY_ZUSTATEK_NIKDY_NEMEL_UCAST, $obsahLogu);
        self::assertStringContainsString('250', $obsahLogu); // Částka
    }

    /**
     * @test
     */
    public function promlceniPrazdnehoSeznamuNeudelaNic()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();

        $vysledek = $promlceniZustatku->promlcZustatky([], \Uzivatel::SYSTEM);

        self::assertSame(0, $vysledek['pocet']);
        self::assertEquals(0, $vysledek['suma']); // Note: empty list returns int 0, not float 0.0
    }

    /**
     * @test
     */
    public function varovneEmailySeNeodesliKdyzJePrilisBrzy()
    {
        // Nastavíme čas na příliš brzy - 2 měsíce před registrací
        $ted = $this->dejCasRegistraceMinus('2 months');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        $vysledek = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);

        self::assertSame(-1, $vysledek, 'E-maily by se neměly odeslat, pokud je příliš brzy');
    }

    /**
     * @test
     */
    public function varovneEmailySeNeodesliKdyzJePrilisPozde()
    {
        // Nastavíme čas na příliš pozdě - den před registrací
        $ted = $this->dejCasRegistraceMinus('1 day');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        $vysledek = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);

        self::assertSame(-1, $vysledek, 'E-maily by se neměly odeslat, pokud je příliš pozdě');
    }

    /**
     * @test
     */
    public function varovneEmailyMesicSeOdesliVeSpravnyCas()
    {
        // Nastavíme přesný čas - 1 měsíc před registrací
        $ted = $this->dejCasRegistraceMinus('1 month');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        // Ujistíme se, že existují uživatelé k promlčení
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();
        self::assertNotEmpty($uzivatele, 'Měli bychom mít uživatele k promlčení');

        $vysledek = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);

        self::assertGreaterThanOrEqual(0, $vysledek, 'Měly by se odeslat e-maily (počet >= 0)');
    }

    /**
     * @test
     */
    public function varovneEmailyTydenSeOdesliVeSpravnyCas()
    {
        // Nastavíme přesný čas - 1 týden před registrací
        $ted = $this->dejCasRegistraceMinus('1 week');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        // Ujistíme se, že existují uživatelé k promlčení
        $uzivatele = $promlceniZustatku->najdiUzivateleKPromlceni();
        self::assertNotEmpty($uzivatele, 'Měli bychom mít uživatele k promlčení');

        $vysledek = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::TYDEN);

        self::assertGreaterThanOrEqual(0, $vysledek, 'Měly by se odeslat e-maily (počet >= 0)');
    }

    /**
     * @test
     */
    public function varovneEmailySeNeodesliPodruheBezParametruZnovu()
    {
        $ted = $this->dejCasRegistraceMinus('1 month');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        // První odeslání
        $vysledek1 = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);
        self::assertGreaterThanOrEqual(0, $vysledek1, 'První odeslání by mělo proběhnout');

        // Druhé odeslání bez parametru znovu - mělo by vrátit -1
        $vysledek2 = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);
        self::assertSame(-1, $vysledek2, 'Druhé odeslání bez parametru znovu by mělo vrátit -1');
    }

    /**
     * @test
     */
    public function varovneEmailySeOdesliPodruheSParametremZnovu()
    {
        $ted = $this->dejCasRegistraceMinus('1 month');
        $promlceniZustatku = $this->dejPromlceniZustatkuSCasem($ted);

        // První odeslání
        $vysledek1 = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC);
        self::assertGreaterThanOrEqual(0, $vysledek1, 'První odeslání by mělo proběhnout');

        // Druhé odeslání s parametrem znovu
        $vysledek2 = $promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::MESIC, znovu: true);
        self::assertGreaterThanOrEqual(0, $vysledek2, 'Druhé odeslání s parametrem znovu by mělo proběhnout');
    }

    /**
     * @test
     */
    public function automatickaPromlceniVratiNullKdyzNebylaProvedena()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();

        // Použijeme neexistující ročník pro test
        $neexistujiciRocnik = ROCNIK + 100;
        $kdy = $promlceniZustatku->automatickaPromlceniProvedenaKdy($neexistujiciRocnik);

        self::assertNull($kdy, 'Pro neexistující ročník by mělo vrátit null');
    }

    /**
     * @test
     */
    public function automatickaPromlceniVratiDatumKdyzBylaProvedena()
    {
        $promlceniZustatku = $this->dejPromlceniZustatku();

        // Zalogujeme automatické promlčení
        $promlceniZustatku->zalogujAutomatickePromlceni(ROCNIK, 5, 1000.0);

        // Zkontrolujeme, že vrací datum
        $kdy = $promlceniZustatku->automatickaPromlceniProvedenaKdy(ROCNIK);

        self::assertNotNull($kdy, 'Po zalogování by mělo vrátit datum');
        self::assertInstanceOf(\DateTimeInterface::class, $kdy);
    }

    // ==================== Helper methods ====================

    private function dejPromlceniZustatku(): PromlceniZustatku
    {
        return new PromlceniZustatku(
            SystemoveNastaveni::zGlobals(),
            $this->createMock(JobResultLoggerInterface::class),
        );
    }

    private function dejPromlceniZustatkuSCasem(DateTimeImmutableStrict $ted): PromlceniZustatku
    {
        $systemoveNastaveni = $this->dejSystemoveNastaveniSCasem($ted);

        return new PromlceniZustatku(
            $systemoveNastaveni,
            $this->createMock(JobResultLoggerInterface::class),
        );
    }

    private function dejSystemoveNastaveniSCasem(DateTimeImmutableStrict $ted): SystemoveNastaveni
    {
        return SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: $ted,
        );
    }

    private function dejCasRegistraceMinus(string $offset): DateTimeImmutableStrict
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $registraceOd = $systemoveNastaveni->prihlasovaniUcastnikuOd(ROCNIK);

        return DateTimeImmutableStrict::createFromInterface($registraceOd)->modify("-{$offset}");
    }

    private function zajistiLogyAdresar(): void
    {
        if (! is_dir(LOGY) && ! @mkdir(LOGY, 0777, true)) {
            self::markTestSkipped('Nelze vytvořit adresář pro logy: ' . LOGY);
        }
    }
}
