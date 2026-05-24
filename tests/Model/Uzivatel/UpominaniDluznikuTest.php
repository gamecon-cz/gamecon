<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Logger\JobResultLoggerInterface;
use Gamecon\Role\Role;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Dto\Dluznik;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\UpominaniDluzniku;

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
        $rocnik = ROCNIK;

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
        int $idUzivatele,
        string $jmeno,
        string $prijmeni,
        float $zustatek,
        string $email,
        int $statUzivatele = Stat::CZ_ID,
    ): string {
        $login = strtolower(str_replace(' ', '_', $jmeno . '_' . $prijmeni));

        return <<<SQL
INSERT INTO uzivatele_hodnoty
SET id_uzivatele = {$idUzivatele},
    login_uzivatele = '{$login}',
    jmeno_uzivatele = '{$jmeno}',
    prijmeni_uzivatele = '{$prijmeni}',
    email1_uzivatele = '{$email}',
    stat_uzivatele = {$statUzivatele},
    zustatek = {$zustatek}
SQL;
    }

    private static function prihlasenNaLetosniGcQuery(int $idUzivatele): string
    {
        $idRole = Role::PRIHLASEN_NA_LETOSNI_GC;

        return <<<SQL
INSERT INTO platne_role_uzivatelu(id_uzivatele, id_role, posadil)
VALUES ({$idUzivatele}, {$idRole}, 1)
SQL;
    }

    // ==================== Tests for najdiDluzniky() ====================

    /**
     * @test
     */
    public function najdeDluznikySeZapornymZustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn (Dluznik $d) => $d->uzivatel->id(),
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
    public function dluznikMaKladnouHodnotuDluhu()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici = $upominaniDluzniku->najdiDluzniky();

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
    public function nenajdeUzivateleSKladnymZustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn (Dluznik $d) => $d->uzivatel->id(),
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
    public function nenajdeUzivateleSNulovymZustatkem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn (Dluznik $d) => $d->uzivatel->id(),
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
    public function upominkySeNeodesliKdyzJePrilisBrzy()
    {
        // Nastavíme čas na den po konci GC - příliš brzy (potřebujeme týden)
        $ted = $this->dejCasKonecGcPlus('1 day');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        self::assertSame(-1, $vysledek, 'Upomínky by se neměly odeslat, pokud je příliš brzy');
    }

    /**
     * @test
     */
    public function upominkySeNeodesliKdyzJePrilisPozde()
    {
        // Nastavíme čas na 2 měsíce po konci GC - příliš pozdě
        $ted = $this->dejCasKonecGcPlus('2 months');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        $vysledek = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        self::assertSame(-1, $vysledek, 'Upomínky by se neměly odeslat, pokud je příliš pozdě');
    }

    /**
     * @test
     */
    public function upominkyTydenSeOdesliVeSpravnyCas()
    {
        // Nastavíme přesný čas - 1 týden po konci GC
        $ted = $this->dejCasKonecGcPlus('1 week');
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
    public function upominkyMesicSeOdesliVeSpravnyCas()
    {
        // Nastavíme přesný čas - 1 měsíc po konci GC
        $ted = $this->dejCasKonecGcPlus('1 month');
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
    public function upominkySeNeodesliPodruheBezParametruZnovu()
    {
        $ted = $this->dejCasKonecGcPlus('1 week');
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
    public function upominkySeOdesliPodruheSParametremZnovu()
    {
        $ted = $this->dejCasKonecGcPlus('1 week');
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
    public function zalogujeUpominaniTydenALzeHoZpetnePrecist()
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
    public function zalogujeUpominaniMesicALzeHoZpetnePrecist()
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

    /**
     * @test
     */
    public function ceskemuUzivateliSeProUpominkuPriloziJenCeskyQrKod()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dejQrKodyProUpominku = new \ReflectionMethod($upominaniDluzniku, 'dejQrKodyProUpominku');
        $dejQrKodyProUpominku->setAccessible(true);

        $uzivatel = $this->vytvorUzivateleSeStatem(Stat::CZ_ID);

        $qrKody = $dejQrKodyProUpominku->invoke($upominaniDluzniku, $uzivatel);

        self::assertSame(['qr-platba-cz.png'], array_keys($qrKody));
    }

    /**
     * @test
     *
     * @dataProvider poskytniStatySeVsemiQrKody
     */
    public function slovenskemuNeboJinemuUzivateliSeProUpominkuPriloziVsechnyTriQrKody(?int $statUzivatele)
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dejQrKodyProUpominku = new \ReflectionMethod($upominaniDluzniku, 'dejQrKodyProUpominku');
        $dejQrKodyProUpominku->setAccessible(true);

        $uzivatel = $this->vytvorUzivateleSeStatem($statUzivatele);

        $qrKody = $dejQrKodyProUpominku->invoke($upominaniDluzniku, $uzivatel);

        self::assertSame(
            ['qr-platba-cz.png', 'qr-platba-sk.png', 'qr-platba-sepa.png'],
            array_keys($qrKody),
        );
    }

    public static function poskytniStatySeVsemiQrKody(): array
    {
        return [
            'slovak' => [Stat::SK_ID],
            'jiny'   => [Stat::JINY_ID],
        ];
    }

    // ==================== Helper methods ====================

    private function dejUpominaniDluzniku(): UpominaniDluzniku
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $jobResultLogger = $this->createMock(JobResultLoggerInterface::class);

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
        $jobResultLogger = $this->createMock(JobResultLoggerInterface::class);

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
        $konecGc = $systemoveNastaveni->spocitanyKonecLetosnihoGameconu();

        return $konecGc->modify("+{$offset}");
    }

    private function vytvorUzivateleSeStatem(?int $statUzivatele): \Uzivatel
    {
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'    => 'upominani_qr_' . time() . '_' . random_int(1000, 9999),
            'jmeno_uzivatele'    => 'Upominani',
            'prijmeni_uzivatele' => 'Qr',
            'email1_uzivatele'   => 'upominani.qr.' . time() . '.' . random_int(1000, 9999) . '@test.cz',
            'stat_uzivatele'     => $statUzivatele,
        ]);

        return \Uzivatel::zId((int) dbInsertId());
    }
}
