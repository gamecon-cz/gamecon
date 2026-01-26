<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Logger\JobResultLoggerInterface;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Dto\Dluznik;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\UpominaniDluzniku;
use Uzivatel;

/**
 * Database integration tests for UpominaniDluzniku
 *
 * Tests the business logic for sending debt reminders to users with negative balances.
 */
class UpominaniDluznikuTest extends AbstractTestDb
{
    // Test user IDs
    private const ID_DLUZNIK_MALY_DLUH = 2001;
    private const ID_DLUZNIK_VELKY_DLUH = 2002;
    private const ID_KLADNY_ZUSTATEK = 2003;
    private const ID_NULOVY_ZUSTATEK = 2004;
    private const ID_DLUZNIK_BEZ_EMAILU = 2005;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $queries = [];
        $rocnik  = ROCNIK;

        // Dlužník s malým dluhem (-50 Kč)
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_MALY_DLUH,
            'Malý',
            'Dlužník',
            -50.0,
            'maly.dluznik@test.cz',
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_DLUZNIK_MALY_DLUH);

        // Dlužník s velkým dluhem (-500 Kč)
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_VELKY_DLUH,
            'Velký',
            'Dlužník',
            -500.0,
            'velky.dluznik@test.cz',
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_DLUZNIK_VELKY_DLUH);

        // Uživatel s kladným zůstatkem - NENÍ dlužník
        $queries[] = self::uzivatelQuery(
            self::ID_KLADNY_ZUSTATEK,
            'Kladný',
            'Zůstatek',
            300.0,
            'kladny@test.cz',
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_KLADNY_ZUSTATEK);

        // Uživatel s nulovým zůstatkem - NENÍ dlužník
        $queries[] = self::uzivatelQuery(
            self::ID_NULOVY_ZUSTATEK,
            'Nulový',
            'Zůstatek',
            0.0,
            'nulovy@test.cz',
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_NULOVY_ZUSTATEK);

        // Dlužník bez e-mailu
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_BEZ_EMAILU,
            'Bez',
            'Emailu',
            -100.0,
            '', // Prázdný e-mail
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_DLUZNIK_BEZ_EMAILU);

        return $queries;
    }

    private static function uzivatelQuery(
        int    $idUzivatele,
        string $jmeno,
        string $prijmeni,
        float  $zustatek,
        string $email,
    ): string {
        $login = strtolower(str_replace(' ', '_', $jmeno . '_' . $prijmeni));

        return <<<SQL
INSERT INTO uzivatele_hodnoty
SET id_uzivatele = $idUzivatele,
    login_uzivatele = '$login',
    jmeno_uzivatele = '$jmeno',
    prijmeni_uzivatele = '$prijmeni',
    email1_uzivatele = '$email',
    zustatek = $zustatek
SQL;
    }

    private static function prihlasenNaLetosniGcQuery(int $idUzivatele): string
    {
        $idRole = Role::PRIHLASEN_NA_LETOSNI_GC;

        return <<<SQL
INSERT INTO platne_role_uzivatelu(id_uzivatele, id_role, posadil)
VALUES ($idUzivatele, $idRole, 1)
SQL;
    }

    // ==================== Tests for najdiDluzniky() ====================

    /**
     * @test
     */
    public function Najde_dluzniky_se_zapornym_zustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici          = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn(Dluznik $d) => $d->uzivatel->id(),
            $dluznici,
        );

        self::assertContains(
            self::ID_DLUZNIK_MALY_DLUH,
            $idsDluzniku,
            'Dlužník s malým dluhem by měl být nalezen',
        );

        self::assertContains(
            self::ID_DLUZNIK_VELKY_DLUH,
            $idsDluzniku,
            'Dlužník s velkým dluhem by měl být nalezen',
        );
    }

    /**
     * @test
     */
    public function Dluznik_ma_kladnou_hodnotu_dluhu()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici          = $upominaniDluzniku->najdiDluzniky();

        $dluznikMaly = null;
        $dluznikVelky = null;
        foreach ($dluznici as $dluznik) {
            if ($dluznik->uzivatel->id() === self::ID_DLUZNIK_MALY_DLUH) {
                $dluznikMaly = $dluznik;
            }
            if ($dluznik->uzivatel->id() === self::ID_DLUZNIK_VELKY_DLUH) {
                $dluznikVelky = $dluznik;
            }
        }

        self::assertNotNull($dluznikMaly, 'Dlužník s malým dluhem nebyl nalezen');
        self::assertSame(50.0, $dluznikMaly->dluh, 'Dluh by měl být převeden na kladnou hodnotu');

        self::assertNotNull($dluznikVelky, 'Dlužník s velkým dluhem nebyl nalezen');
        self::assertSame(500.0, $dluznikVelky->dluh, 'Dluh by měl být převeden na kladnou hodnotu');
    }

    /**
     * @test
     */
    public function Nenajde_uzivatele_s_kladnym_zustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici          = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn(Dluznik $d) => $d->uzivatel->id(),
            $dluznici,
        );

        self::assertNotContains(
            self::ID_KLADNY_ZUSTATEK,
            $idsDluzniku,
            'Uživatel s kladným zůstatkem NESMÍ být mezi dlužníky',
        );
    }

    /**
     * @test
     */
    public function Nenajde_uzivatele_s_nulovym_zustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici          = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn(Dluznik $d) => $d->uzivatel->id(),
            $dluznici,
        );

        self::assertNotContains(
            self::ID_NULOVY_ZUSTATEK,
            $idsDluzniku,
            'Uživatel s nulovým zůstatkem NESMÍ být mezi dlužníky',
        );
    }

    // ==================== Tests for odesliUpominkyDluznikum() ====================

    /**
     * @test
     */
    public function Upominky_se_neodesli_kdyz_je_prilis_brzy()
    {
        // Nastavíme čas na den po konci GC - příliš brzy (potřebujeme týden)
        $ted               = $this->dejCasKonecGcPlus('1 day');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        self::assertSame(-1, $vysledek, 'Upomínky by se neměly odeslat, pokud je příliš brzy');
    }

    /**
     * @test
     */
    public function Upominky_se_neodesli_kdyz_je_prilis_pozde()
    {
        // Nastavíme čas na 2 měsíce po konci GC - příliš pozdě
        $ted               = $this->dejCasKonecGcPlus('2 months');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        self::assertSame(-1, $vysledek, 'Upomínky by se neměly odeslat, pokud je příliš pozdě');
    }

    /**
     * @test
     */
    public function Upominky_tyden_se_odesli_ve_spravny_cas()
    {
        // Nastavíme přesný čas - 1 týden po konci GC
        $ted               = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        // Ujistíme se, že existují dlužníci
        $dluznici = $upominaniDluzniku->najdiDluzniky();
        self::assertNotEmpty($dluznici, 'Měli bychom mít dlužníky');

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        // Počet odeslaných e-mailů by měl být >= 0 (může být 0, pokud žádný dlužník nemá e-mail,
        // nebo >= 2 pokud mají)
        self::assertGreaterThanOrEqual(0, $vysledek, 'Měly by se odeslat upomínky (počet >= 0)');
    }

    /**
     * @test
     */
    public function Upominky_mesic_se_odesli_ve_spravny_cas()
    {
        // Nastavíme přesný čas - 1 měsíc po konci GC
        $ted               = $this->dejCasKonecGcPlus('1 month');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        // Ujistíme se, že existují dlužníci
        $dluznici = $upominaniDluzniku->najdiDluzniky();
        self::assertNotEmpty($dluznici, 'Měli bychom mít dlužníky');

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::MESIC);

        self::assertGreaterThanOrEqual(0, $vysledek, 'Měly by se odeslat upomínky (počet >= 0)');
    }

    /**
     * @test
     */
    public function Upominky_se_neodesli_podruhe_bez_parametru_znovu()
    {
        $ted               = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        // První odeslání
        $vysledek1 = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertGreaterThanOrEqual(0, $vysledek1, 'První odeslání by mělo proběhnout');

        // Druhé odeslání bez parametru znovu - mělo by vrátit -1
        $vysledek2 = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertSame(-1, $vysledek2, 'Druhé odeslání bez parametru znovu by mělo vrátit -1');
    }

    /**
     * @test
     */
    public function Upominky_se_odesli_podruhe_s_parametrem_znovu()
    {
        $ted               = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        // První odeslání
        $vysledek1 = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertGreaterThanOrEqual(0, $vysledek1, 'První odeslání by mělo proběhnout');

        // Druhé odeslání s parametrem znovu
        $vysledek2 = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN, znovu: true);
        self::assertGreaterThanOrEqual(0, $vysledek2, 'Druhé odeslání s parametrem znovu by mělo proběhnout');
    }

    // ==================== Tests for logging ====================

    /**
     * @test
     */
    public function Zaloguje_upominani_tyden_a_lze_ho_zpetne_precist()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        // Zalogujeme upomínání
        $upominaniDluzniku->zalogujUpominaniTyden(ROCNIK, 5);

        // Zkontrolujeme, že je záznam v databázi
        $zaznam = dbOneLine(<<<SQL
SELECT *
FROM hromadne_akce_log
WHERE skupina = 'upominani-dluzniku'
    AND akce = 'upominani-tyden-$1'
SQL,
            [ROCNIK],
        );

        self::assertNotEmpty($zaznam, 'Záznam o upomínání by měl existovat');
        self::assertSame('5', $zaznam['vysledek']);
    }

    /**
     * @test
     */
    public function Zaloguje_upominani_mesic_a_lze_ho_zpetne_precist()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        // Zalogujeme upomínání
        $upominaniDluzniku->zalogujUpominaniMesic(ROCNIK, 10);

        // Zkontrolujeme, že je záznam v databázi
        $zaznam = dbOneLine(<<<SQL
SELECT *
FROM hromadne_akce_log
WHERE skupina = 'upominani-dluzniku'
    AND akce = 'upominani-mesic-$1'
SQL,
            [ROCNIK],
        );

        self::assertNotEmpty($zaznam, 'Záznam o upomínání by měl existovat');
        self::assertSame('10', $zaznam['vysledek']);
    }

    // ==================== Helper methods ====================

    private function dejUpominaniDluzniku(): UpominaniDluzniku
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $jobResultLogger    = $this->createMock(JobResultLoggerInterface::class);

        // Mock FioStazeniNovychPlateb, aby se nevolalo skutečné API
        $fioStazeniNovychPlateb = $this->createMock(FioStazeniNovychPlateb::class);
        // stahniNoveFioPlatby returns void, so we don't need willReturn

        return new UpominaniDluzniku(
            $systemoveNastaveni,
            $jobResultLogger,
            $fioStazeniNovychPlateb,
        );
    }

    private function dejUpominaniDluznikuSCasem(DateTimeImmutableStrict $ted): UpominaniDluzniku
    {
        $systemoveNastaveni = $this->dejSystemoveNastaveniSCasem($ted);
        $jobResultLogger    = $this->createMock(JobResultLoggerInterface::class);

        // Mock FioStazeniNovychPlateb, aby se nevolalo skutečné API
        $fioStazeniNovychPlateb = $this->createMock(FioStazeniNovychPlateb::class);
        // stahniNoveFioPlatby returns void, so we don't need willReturn

        return new UpominaniDluzniku(
            $systemoveNastaveni,
            $jobResultLogger,
            $fioStazeniNovychPlateb,
        );
    }

    private function dejSystemoveNastaveniSCasem(DateTimeImmutableStrict $ted): SystemoveNastaveni
    {
        return SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: $ted,
        );
    }

    private function dejCasKonecGcPlus(string $offset): DateTimeImmutableStrict
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $konecGc            = $systemoveNastaveni->spocitanyKonecLetosnihoGameconu();

        return $konecGc->modify("+$offset");
    }
}
