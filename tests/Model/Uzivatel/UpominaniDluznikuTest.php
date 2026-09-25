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
use Gamecon\Uzivatel\Enum\UcastNaGc;
use Gamecon\Uzivatel\Exceptions\VlastniZneniNeniVyplnene;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\UpominaniDluzniku;
use Gamecon\Uzivatel\UpominkaVlastniZneni;

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
    private const ID_DLUZNIK_Z_MINULYCH_LET = 2006;
    private const ID_DLUZNIK_PRITOMNY = 2007;
    private const ID_DLUZNICE_NEDORAZILA = 2008;
    private const ID_DLUZNIK_JEN_ZAPORNA_PLATBA = 2009;
    private const ROK_POSLEDNI_UCASTI_STAREHO_DLUZNIKA = 2019;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $queries = [];
        $rocnik = ROCNIK;
        $idVratkoveho = self::ID_DLUZNIK_JEN_ZAPORNA_PLATBA;

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

        // Dlužník z minulých let - záporný zůstatek, letos ani přihlášený, ani přítomný.
        // Upomínka mu jít má, ale textem, který mu netvrdí, že letos na GC byl.
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_Z_MINULYCH_LET,
            'Loňský',
            'Dlužník',
            -300.0,
            'lonsky.dluznik@test.cz',
        );
        $queries[] = self::roleUcastiRocnikuQuery(self::ROK_POSLEDNI_UCASTI_STAREHO_DLUZNIKA);
        $queries[] = self::pritomenNaRocnikuQuery(
            self::ID_DLUZNIK_Z_MINULYCH_LET,
            self::ROK_POSLEDNI_UCASTI_STAREHO_DLUZNIKA,
        );

        // Dlužník, který letos opravdu dorazil (prošel infopultem)
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_PRITOMNY,
            'Přítomný',
            'Dlužník',
            -80.0,
            'pritomny.dluznik@test.cz',
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_DLUZNIK_PRITOMNY);
        $queries[] = self::pritomenNaRocnikuQuery(self::ID_DLUZNIK_PRITOMNY, ROCNIK);

        // Dlužnice přihlášená, ale nepřítomná - na ní se ověřuje skloňování textu
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNICE_NEDORAZILA,
            'Nepřítomná',
            'Dlužnice',
            -120.0,
            'nepritomna.dluznice@test.cz',
            Stat::CZ_ID,
            Pohlavi::ZENA_KOD,
        );
        $queries[] = self::prihlasenNaLetosniGcQuery(self::ID_DLUZNICE_NEDORAZILA);

        // Dlužník bez letošní přihlášky i bez záporného sloupce zustatek - do mínusu
        // ho dostane až záporná platba (vratka). Hlídá, že ho předfiltr nevynechá.
        $queries[] = self::uzivatelQuery(
            self::ID_DLUZNIK_JEN_ZAPORNA_PLATBA,
            'Vratkový',
            'Dlužník',
            0.0,
            'vratkovy.dluznik@test.cz',
        );
        $queries[] = <<<SQL
INSERT INTO platby(id_uzivatele, castka, rok, provedeno, provedl)
VALUES ({$idVratkoveho}, -140, {$rocnik}, NOW(), 1)
SQL;

        return $queries;
    }

    private static function uzivatelQuery(
        int $idUzivatele,
        string $jmeno,
        string $prijmeni,
        float $zustatek,
        string $email,
        int $statUzivatele = Stat::CZ_ID,
        string $pohlavi = Pohlavi::MUZ_KOD,
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
    pohlavi = '{$pohlavi}',
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

    private static function pritomenNaRocnikuQuery(
        int $idUzivatele,
        int $rocnik,
    ): string {
        $idRole = Role::pritomenNaRocniku($rocnik);

        return <<<SQL
INSERT INTO platne_role_uzivatelu(id_uzivatele, id_role, posadil)
VALUES ({$idUzivatele}, {$idRole}, 1)
SQL;
    }

    /**
     * Migracemi postavená testovací DB zná jen role letošního ročníku, takže si
     * roli staršího ročníku musí test založit sám.
     */
    private static function roleUcastiRocnikuQuery(int $rocnik): string
    {
        $idRole = Role::pritomenNaRocniku($rocnik);
        $kod = "GC{$rocnik}_PRITOMEN";
        $typ = Role::TYP_UCAST;

        return <<<SQL
INSERT IGNORE INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ({$idRole}, '{$kod}', '{$kod}', '{$kod}', {$rocnik}, '{$typ}', '')
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

    /**
     * @test
     */
    public function najdeIDluznikaBezLetosniPrihlasky()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznici = $upominaniDluzniku->najdiDluzniky();

        $idsDluzniku = array_map(
            fn (Dluznik $d) => $d->uzivatel->id(),
            $dluznici,
        );

        self::assertContains(
            self::ID_DLUZNIK_Z_MINULYCH_LET,
            $idsDluzniku,
            'Upomínka má jít každému dlužníkovi bez ohledu na letošní účast',
        );
    }

    /**
     * @test
     */
    public function dluznikNeseJakSeLetosZucastnil()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $podleId = [];
        foreach ($upominaniDluzniku->najdiDluzniky() as $dluznik) {
            $podleId[$dluznik->uzivatel->id()] = $dluznik;
        }

        self::assertSame(
            UcastNaGc::JEN_PRIHLASEN,
            $podleId[self::ID_DLUZNIK_MALY_DLUH]->ucastNaGc,
            'Přihlášený, ale neodbavený na infopultu, letos nedorazil',
        );
        self::assertSame(
            UcastNaGc::PRITOMEN,
            $podleId[self::ID_DLUZNIK_PRITOMNY]->ucastNaGc,
        );
        self::assertSame(
            UcastNaGc::NEDORAZIL,
            $podleId[self::ID_DLUZNIK_Z_MINULYCH_LET]->ucastNaGc,
            'Bez letošní přihlášky i účasti',
        );
    }

    /**
     * @test
     */
    public function dluznikNeseRokPosledniUcasti()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $podleId = [];
        foreach ($upominaniDluzniku->najdiDluzniky() as $dluznik) {
            $podleId[$dluznik->uzivatel->id()] = $dluznik;
        }

        self::assertSame(
            self::ROK_POSLEDNI_UCASTI_STAREHO_DLUZNIKA,
            $podleId[self::ID_DLUZNIK_Z_MINULYCH_LET]->rokPosledniUcasti,
        );
        self::assertSame(
            ROCNIK,
            $podleId[self::ID_DLUZNIK_PRITOMNY]->rokPosledniUcasti,
        );
        self::assertNull(
            $podleId[self::ID_DLUZNIK_MALY_DLUH]->rokPosledniUcasti,
            'Kdo na GC nikdy nebyl, nemá rok poslední účasti',
        );
    }

    /**
     * @test
     */
    public function dejDluznikaVraciTotezCoHromadneHledani()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $hromadne = [];
        foreach ($upominaniDluzniku->najdiDluzniky() as $dluznik) {
            $hromadne[$dluznik->uzivatel->id()] = $dluznik->dluh;
        }

        // najdiDluzniky() si kvůli rychlosti předfiltrovává, koho vůbec přepočítá.
        // Kdyby filtr někoho vynechal, jednotlivý dotaz by ho našel a tenhle test padne.
        foreach ([self::ID_DLUZNIK_VELKY_DLUH, self::ID_DLUZNIK_Z_MINULYCH_LET, self::ID_DLUZNICE_NEDORAZILA] as $idUzivatele) {
            $jednotlive = $upominaniDluzniku->dejDluznika(\Uzivatel::zId($idUzivatele));

            self::assertNotNull($jednotlive, "Uživatel {$idUzivatele} je dlužník");
            self::assertSame(
                $hromadne[$idUzivatele] ?? null,
                $jednotlive->dluh,
                "Hromadné hledání musí najít uživatele {$idUzivatele} se stejným dluhem",
            );
        }
    }

    /**
     * @test
     */
    public function najdeDluznikaKterehoDoMinusuDostalaAzZapornaPlatba()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $idsDluzniku = array_map(
            fn (Dluznik $dluznik) => $dluznik->uzivatel->id(),
            $upominaniDluzniku->najdiDluzniky(),
        );

        // Nemá letošní přihlášku ani záporný sloupec zustatek, do mínusu ho
        // dostane jen vratka - předfiltr ho proto nesmí vynechat.
        self::assertContains(
            self::ID_DLUZNIK_JEN_ZAPORNA_PLATBA,
            $idsDluzniku,
            'Dlužník vzniklý zápornou platbou musí být v seznamu',
        );
    }

    /**
     * @test
     */
    public function dejDluznikaVraciNullProNedluznika()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        self::assertNull($upominaniDluzniku->dejDluznika(\Uzivatel::zId(self::ID_KLADNY_ZUSTATEK)));
        self::assertNull($upominaniDluzniku->dejDluznika(\Uzivatel::zId(\Uzivatel::SYSTEM, true)));
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

        // Druhé odeslání s parametrem znovu - musí obeslat stejné lidi jako první běh,
        // jinak přeskakování už obeslaných celý parametr znovu umlčí.
        $vysledek2 = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN, znovu: true);
        self::assertSame(
            $vysledek1,
            $vysledek2,
            'S parametrem znovu se musí obeslat i ti, kdo už upomínku dostali',
        );
    }

    /**
     * @test
     */
    public function automatikaPreskociDluhPodPrahem()
    {
        // Očekávání je napsané natvrdo podle fixtur (dluhy 500 a 300 Kč nad prahem
        // 251, zbytek pod ním). Kdyby se očekávání dopočítávalo stejným výrazem
        // jako v kódu, test by prošel i s prahem tiše spadlým na nulu.
        self::assertSame(
            251.0,
            SystemoveNastaveni::zGlobals()->upominkaMinimalniCastka(),
            'Test počítá s prahem 251 Kč z migrace',
        );

        $ted = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        self::assertSame(
            2,
            $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN),
            'Odejít smí jen dlužníci s dluhem 500 a 300 Kč',
        );

        $obeslani = array_map(
            'intval',
            dbFetchColumn(
                'SELECT id_uzivatele FROM upominka_dluznika_log WHERE rocnik = $0 ORDER BY id_uzivatele',
                [
                    0 => ROCNIK,
                ],
            ),
        );

        self::assertSame(
            [self::ID_DLUZNIK_VELKY_DLUH, self::ID_DLUZNIK_Z_MINULYCH_LET],
            $obeslani,
            'Pod prahem nesmí upomínka odejít nikomu',
        );
    }

    /**
     * @test
     */
    public function prahSeBereZeSystemovehoNastaveni()
    {
        // Hodnota musí dojít až do accessoru - se `vlastni = 0` by se místo
        // uložené hodnoty vzala výchozí a práh by tiše spadl na nulu.
        $ulozenaHodnota = (float) dbFetchSingle(
            'SELECT hodnota FROM systemove_nastaveni WHERE klic = $0',
            [
                0 => 'UPOMINKA_MINIMALNI_CASTKA',
            ],
        );

        self::assertGreaterThan(0.0, $ulozenaHodnota, 'Migrace musí práh naplnit nenulovou hodnotou');
        self::assertSame(
            $ulozenaHodnota,
            SystemoveNastaveni::zGlobals()->upominkaMinimalniCastka(),
        );
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
     *
     * @dataProvider poskytniUcastiBezLetosniPritomnosti
     */
    public function textNetvrdiLetosniUcastTomuKdoNedorazil(UcastNaGc $ucastNaGc)
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $zprava = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            $ucastNaGc,
            null,
            '',
            'Tester',
            ROCNIK,
        );

        self::assertStringNotContainsString(
            'letošní GameCon bavil',
            $zprava,
            'Kdo letos na GC nebyl, nesmí dostat text o tom, jak ho letošní GC bavil',
        );
        self::assertStringNotContainsString(
            'dotazniky',
            $zprava,
            'Zpětnou vazbu na letošní GC má smysl chtít jen po tom, kdo tu byl',
        );
    }

    public static function poskytniUcastiBezLetosniPritomnosti(): array
    {
        return [
            'jen prihlasen' => [UcastNaGc::JEN_PRIHLASEN],
            'nedorazil'     => [UcastNaGc::NEDORAZIL],
        ];
    }

    /**
     * @test
     */
    public function textNeslibujeDrivejsiUcastTomuKdoNaGcNikdyNebyl()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $nikdyNebyl = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            UcastNaGc::NEDORAZIL,
            null,
            '',
            'Tester',
            ROCNIK,
        );
        // Závěrečné „snad se uvidíme na některém z dalších ročníků“ je v pořádku,
        // hlídá se jen tvrzení, že dluh pochází z nějaké dřívější účasti.
        self::assertStringNotContainsString(
            'dřívějších ročníků',
            $nikdyNebyl,
            'Kdo na GC nikdy nebyl, nesmí dostat text o dluhu z dřívějšího ročníku',
        );
        self::assertStringNotContainsString(
            'Z GameConu',
            $nikdyNebyl,
            'Bez známé účasti se nesmí tvrdit konkrétní ročník',
        );

        $bylDrive = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            UcastNaGc::NEDORAZIL,
            2019,
            '',
            'Tester',
            ROCNIK,
        );
        self::assertStringContainsString(
            'GameConu 2019',
            $bylDrive,
            'Kdo byl naposledy v roce 2019, má se dozvědět, odkud dluh je',
        );
    }

    /**
     * @test
     */
    public function textSeSklonujePodlePohlavi()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $zene = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            UcastNaGc::JEN_PRIHLASEN,
            null,
            'a',
            'Tester',
            ROCNIK,
        );
        self::assertStringContainsString('nedostala.', $zene);

        $muzi = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            UcastNaGc::JEN_PRIHLASEN,
            null,
            '',
            'Tester',
            ROCNIK,
        );
        self::assertStringContainsString('nedostal.', $muzi);

        self::assertStringNotContainsString(
            'nedostal/a',
            $zene . $muzi,
            'Text se skloňuje podle pohlaví, nepoužívá se lomítková varianta',
        );
    }

    /**
     * @test
     */
    public function odeslanyMailZeneJeSklonovanyPodleJejihoPohlavi()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        // Přes odesliUpominkuJednomu, aby se ověřilo i napojení na Uzivatel::pohlavi()
        $gcMail = $upominaniDluzniku->odesliUpominkuJednomu(
            \Uzivatel::zId(self::ID_DLUZNICE_NEDORAZILA),
            TypUpominky::TYDEN,
            120,
            ROCNIK,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true),
            UcastNaGc::JEN_PRIHLASEN,
            null,
        );

        self::assertStringContainsString(
            'nedostala.',
            $gcMail->dejText(),
            'Ženě musí dojít text skloňovaný podle jejího pohlaví',
        );
    }

    /**
     * @test
     */
    public function pritomnemuUcastnikoviZustavaPuvodniText()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $zprava = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            250,
            12345,
            UcastNaGc::PRITOMEN,
            ROCNIK,
            '',
            'Tester',
            ROCNIK,
        );

        self::assertStringContainsString('letošní GameCon bavil', $zprava);
        self::assertStringContainsString('last moment aktivity', $zprava);
    }

    /**
     * @test
     */
    public function systemovyUcetNedostaneUpominku()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $idsDluzniku = array_map(
            fn (Dluznik $dluznik) => $dluznik->uzivatel->id(),
            $upominaniDluzniku->najdiDluzniky(),
        );

        self::assertNotContains(
            \Uzivatel::SYSTEM,
            $idsDluzniku,
            'Systémový účet není člověk a upomínku dostat nesmí',
        );
    }

    /**
     * @test
     */
    public function vlastniZneniNahradiStandardniTextIPredmet()
    {
        $upominkaVlastniZneni = new UpominkaVlastniZneni();
        $upominkaVlastniZneni->uloz(
            ROCNIK,
            'Nedoplatek GameCon %ROCNIK%',
            'Ahoj {jmeno}, dluh {dluh} Kč pošli na %UCET_CZ% pod VS {vs}. Nezapomněl{a} jsi?',
            \Uzivatel::SYSTEM,
        );

        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $zprava = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::VLASTNI,
            250,
            12345,
            UcastNaGc::PRITOMEN,
            ROCNIK,
            'a',
            'Tester',
            ROCNIK,
        );

        self::assertSame(
            'Ahoj Tester, dluh 250 Kč pošli na ' . UCET_CZ . ' pod VS 12345. Nezapomněla jsi?',
            $zprava,
            'Vlastní znění musí projít dosazením {…} symbolů i %KONSTANT%',
        );
        self::assertStringNotContainsString(
            'krásné vzpomínky',
            $zprava,
            'Vlastní znění nahrazuje standardní text, nepřidává se k němu',
        );

        self::assertSame(
            'Nedoplatek GameCon ' . ROCNIK,
            $upominaniDluzniku->dejEmailPredmet(TypUpominky::VLASTNI, ROCNIK),
            'V předmětu se musí dosadit konstanty stejně jako v textu',
        );
    }

    /**
     * @test
     */
    public function vlastniZneniNedosadiTajneKonstanty()
    {
        self::assertTrue(
            defined('FIO_TOKEN'),
            'Test má smysl jen když je taková konstanta vůbec definovaná',
        );

        (new UpominkaVlastniZneni())->uloz(
            ROCNIK,
            'Nedoplatek %FIO_TOKEN%',
            'Účet %UCET_CZ%, token %FIO_TOKEN%, heslo %DB_PASS%.',
            \Uzivatel::SYSTEM,
        );

        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $zprava = $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::VLASTNI,
            250,
            12345,
            UcastNaGc::PRITOMEN,
            ROCNIK,
            '',
            'Tester',
            ROCNIK,
        );
        $predmet = $upominaniDluzniku->dejEmailPredmet(TypUpominky::VLASTNI, ROCNIK);

        self::assertSame(
            'Účet ' . UCET_CZ . ', token %FIO_TOKEN%, heslo %DB_PASS%.',
            $zprava,
            'Upomínka odchází lidem mimo organizaci, takže se smí dosadit jen konstanty z allowlistu',
        );
        self::assertSame(
            'Nedoplatek %FIO_TOKEN%',
            $predmet,
            'Allowlist platí i pro předmět',
        );
        self::assertStringNotContainsString((string) FIO_TOKEN, $zprava . $predmet);
    }

    /**
     * @test
     *
     * @dataProvider poskytniNevyplnenaZneni
     */
    public function nevyplneneVlastniZneniNedovoliOdeslat(
        string $predmet,
        string $text,
    ) {
        (new UpominkaVlastniZneni())->uloz(ROCNIK, $predmet, $text, \Uzivatel::SYSTEM);

        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $this->expectException(VlastniZneniNeniVyplnene::class);

        $upominaniDluzniku->odesliUpominkuJednomu(
            \Uzivatel::zId(self::ID_DLUZNICE_NEDORAZILA),
            TypUpominky::VLASTNI,
            120,
            ROCNIK,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true),
            UcastNaGc::JEN_PRIHLASEN,
            null,
        );
    }

    /**
     * @test
     *
     * @dataProvider poskytniNevyplnenaZneni
     */
    public function nevyplneneVlastniZneniNevykresliAniPredmetAniText(
        string $predmet,
        string $text,
    ) {
        (new UpominkaVlastniZneni())->uloz(ROCNIK, $predmet, $text, \Uzivatel::SYSTEM);

        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        try {
            $upominaniDluzniku->dejEmailPredmet(TypUpominky::VLASTNI, ROCNIK);
            self::fail('Předmět z nevyplněného znění se nesmí vůbec vyrobit');
        } catch (VlastniZneniNeniVyplnene) {
            // očekávané
        }

        $this->expectException(VlastniZneniNeniVyplnene::class);
        $upominaniDluzniku->dejEmailZpravu(
            TypUpominky::VLASTNI,
            250,
            12345,
            UcastNaGc::PRITOMEN,
            ROCNIK,
            '',
            'Tester',
            ROCNIK,
        );
    }

    /**
     * @test
     */
    public function zadneVlastniZneniNedovoliOdeslat()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $this->expectException(VlastniZneniNeniVyplnene::class);

        $upominaniDluzniku->dejEmailPredmet(TypUpominky::VLASTNI, ROCNIK);
    }

    public static function poskytniNevyplnenaZneni(): array
    {
        return [
            'oboji prazdne'    => ['', ''],
            'chybi text'       => ['Vlastní předmět', ''],
            'chybi predmet'    => ['', 'Nějaký text'],
            'text jen z mezer' => ['Vlastní předmět', "  \n  "],
        ];
    }

    /**
     * @test
     */
    public function vlastniZneniDojdeIRealneOdeslanymMailem()
    {
        (new UpominkaVlastniZneni())->uloz(
            ROCNIK,
            'Vlastní předmět',
            'Ahoj {jmeno}, dluh {dluh} Kč pod VS {vs}.',
            \Uzivatel::SYSTEM,
        );

        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $gcMail = $upominaniDluzniku->odesliUpominkuJednomu(
            \Uzivatel::zId(self::ID_DLUZNICE_NEDORAZILA),
            TypUpominky::VLASTNI,
            120,
            ROCNIK,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true),
            UcastNaGc::JEN_PRIHLASEN,
            null,
        );

        self::assertStringContainsString(
            'dluh 120 Kč',
            $gcMail->dejText(),
            'Reálně odeslaný mail musí mít dosazené vlastní znění, ne jen náhled',
        );
        self::assertSame('Vlastní předmět', $gcMail->dejPredmet());
    }

    /**
     * @test
     */
    public function kazdaOdeslanaUpominkaSeZalogujeZvlast()
    {
        $ted = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        // Očekávaný počet se bere ze seznamu dlužníků, ne z návratové hodnoty běhu -
        // jinak by test prošel i tehdy, kdyby se většina dlužníků omylem přeskočila.
        $minimalniCastka = SystemoveNastaveni::zGlobals()->upominkaMinimalniCastka();
        $dluzniciSMailem = array_filter(
            $upominaniDluzniku->najdiDluzniky(),
            static fn (Dluznik $dluznik) => $dluznik->uzivatel->mail()
                && $dluznik->dluh >= $minimalniCastka,
        );
        self::assertNotEmpty($dluzniciSMailem, 'Test potřebuje aspoň jednoho dlužníka s e-mailem nad prahem');

        $pocetOdeslanych = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertSame(
            count($dluzniciSMailem),
            $pocetOdeslanych,
            'Upomínka musí odejít každému dlužníkovi s e-mailem nad prahem',
        );

        $pocetZaznamu = (int) dbFetchSingle(<<<SQL
SELECT COUNT(*)
FROM upominka_dluznika_log
WHERE rocnik = $0
SQL,
            [
                0 => ROCNIK,
            ],
        );

        self::assertSame(
            count($dluzniciSMailem),
            $pocetZaznamu,
            'Každý odeslaný e-mail musí mít vlastní řádek v logu, ne jen souhrn za celý běh',
        );
    }

    /**
     * @test
     */
    public function jizUpomenutemuDluznikoviNeprijdeUpominkaPodruhe()
    {
        $ted = $this->dejCasKonecGcPlus('1 week');
        $upominaniDluzniku = $this->dejUpominaniDluznikuSCasem($ted);

        $prvniBeh = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertGreaterThan(0, $prvniBeh, 'Test potřebuje aspoň jednu odeslanou upomínku');

        // Pád uprostřed rozesílky: souhrnný záznam za celý běh nevznikl, takže
        // další běh projde přes jizOdeslano() a smí doslat jen dosud neobeslané.
        dbQuery(
            "DELETE FROM hromadne_akce_log WHERE skupina = 'upominani-dluzniku'",
        );

        $druhyBeh = $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN);

        self::assertSame(
            0,
            $druhyBeh,
            'Už obeslaní dlužníci se musí přeskočit, jinak jim upomínka přijde podruhé',
        );
    }

    /**
     * @test
     */
    public function mesicniUpominkaDojdeIKomuUzPrislaTydenni()
    {
        $tyden = $this->dejUpominaniDluznikuSCasem($this->dejCasKonecGcPlus('1 week'));
        $pocetTyden = $tyden->odesliUpominkyDluznikum(TypUpominky::TYDEN);
        self::assertGreaterThan(0, $pocetTyden, 'Test potřebuje odeslanou týdenní upomínku');

        // Měsíční upomínka je samostatný cron o tři týdny později a míří právě na ty,
        // kdo po týdenní upomínce pořád dluží - nesmí je přeskočit jako „už obeslané“.
        $mesic = $this->dejUpominaniDluznikuSCasem($this->dejCasKonecGcPlus('1 month'));
        $pocetMesic = $mesic->odesliUpominkyDluznikum(TypUpominky::MESIC);

        self::assertSame(
            $pocetTyden,
            $pocetMesic,
            'Měsíční upomínka musí dojít všem, kdo dostali týdenní a stále dluží',
        );
    }

    /**
     * @test
     */
    public function rucniUpominkaSeZalogujeSOdesilatelemAJehoTypem()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();
        $dluznik = \Uzivatel::zId(self::ID_DLUZNIK_MALY_DLUH);
        $odesilatel = \Uzivatel::zId(\Uzivatel::SYSTEM, true);

        $upominaniDluzniku->odesliUpominkuJednomu(
            $dluznik,
            TypUpominky::RUCNI,
            123,
            ROCNIK,
            $odesilatel,
            UcastNaGc::PRITOMEN,
            ROCNIK,
        );

        $zaznam = dbOneLine(<<<SQL
SELECT typ_upominky, dluh, odeslal
FROM upominka_dluznika_log
WHERE id_uzivatele = $0
    AND rocnik = $1
SQL,
            [
                0 => self::ID_DLUZNIK_MALY_DLUH,
                1 => ROCNIK,
            ],
        );

        self::assertNotEmpty($zaznam, 'Ruční upomínka se musí zalogovat');
        self::assertSame(TypUpominky::RUCNI->value, $zaznam['typ_upominky']);
        self::assertSame('123', $zaznam['dluh'], 'Log musí držet částku, která byla v e-mailu');
        self::assertSame((string) $odesilatel->id(), $zaznam['odeslal']);
    }

    /**
     * @test
     */
    public function rucniUpominkaPouzijeTextMesicniVarianty()
    {
        self::assertSame(
            TypUpominky::MESIC,
            TypUpominky::RUCNI->textovaVarianta(),
            'Ruční upomínka nemá vlastní text, bere naléhavější měsíční variantu',
        );
        self::assertSame(TypUpominky::TYDEN, TypUpominky::TYDEN->textovaVarianta());
        self::assertSame(TypUpominky::MESIC, TypUpominky::MESIC->textovaVarianta());
    }

    /**
     * @test
     */
    public function rucniUpominkaSeNesmiRozesilatAutomatickouCestou()
    {
        $upominaniDluzniku = $this->dejUpominaniDluzniku();

        $this->expectException(\LogicException::class);

        $upominaniDluzniku->odesliUpominkyDluznikum(TypUpominky::RUCNI);
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
