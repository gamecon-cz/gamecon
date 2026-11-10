<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\DoplneniZProgramu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Lectures of a finished GameCon go into the archive, every card once, without touching what is there.
 */
class DoplneniZProgramuTest extends AbstractTestDb
{
    // only for the partial fixtures of the program; the job itself runs with STRICT_TRANS_TABLES, see setUp()
    protected static bool $disableStrictTransTables = true;

    private const int ROK = DoplneniZProgramu::PRVNI_ROCNIK;

    /**
     * a card of this title fails on its write into the archive, see setUpBeforeClass()
     */
    private const string NAZEV_SELZE = 'Selže při zápisu';

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = self::ROK;

        return [
            // the main activity first, the instance points to it and the other term of the card to the instance
            <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (990401, 'Přednáška se dvěma termíny - druhý díl', 'dva-terminy-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990403, 'Placený workshop', 'placeny-po-gc', 3, {$rok}, 3, '', 220, 5, 0, 0),
    (990404, 'Proběhne později', 'probehne-pozdeji-po-gc', 3, {$rok}, 5, '', 0, 40, 0, 0),
    (990405, 'Už v archivu', 'uz-v-archivu-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990406, 'Stejný název jako ruční', 'stejny-nazev-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990407, 'RPG hra', 'rpg-po-gc', 4, {$rok}, 7, '', 0, 5, 0, 0),
    (990408, 'Živé hraní na pódiu', 'zive-hrani-po-gc', 3, {$rok}, 7, '', 0, 60, 0, 0),
    (990409, 'Zrušená - !ZRUŠENO!', 'zrusena-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990410, 'Workshop i přednáška', 'workshop-talk-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990411, 'Nikdy nezveřejněná', 'nezverejnena-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990412, 'Zahájení GameConu', 'zahajeni-po-gc', 3, {$rok}, 7, '', 0, 0, 0, 0),
    (990413, 'Hlavní instance jiného typu', 'jiny-typ-po-gc', 4, {$rok}, 7, '', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES (990401, 990401), (990413, 990413)
SQL,
            <<<'SQL'
UPDATE akce_seznam SET patri_pod = id_akce WHERE id_akce IN (990401, 990413)
SQL,
            // the other term of the first card did not take place, one finished term is enough; the term of the last
            // card is a lecture although its main activity is not, an administrator paired the main one
            <<<SQL
INSERT INTO akce_seznam(id_akce, patri_pod, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (990402, 990401, 'Přednáška se dvěma termíny - druhý díl', '', 3, {$rok}, 2, '', 0, 40, 0, 0),
    (990414, 990413, 'Hlavní instance jiného typu', 'jiny-typ-termin-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0)
SQL,
            // the tags may already exist (migrations), they are found by name
            <<<'SQL'
INSERT IGNORE INTO sjednocene_tagy(id_kategorie_tagu, nazev, poznamka)
VALUES
    ((SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'typ Přednášky'), 'Živé hraní', ''),
    ((SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'typ Přednášky'), 'Talk', ''),
    ((SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'typ'), 'Workshop', '')
SQL,
            <<<'SQL'
INSERT INTO akce_sjednocene_tagy(id_akce, id_tagu)
SELECT akce.id_akce, sjednocene_tagy.id
FROM (
    SELECT 990408 AS id_akce, 'Živé hraní' AS nazev
    UNION ALL SELECT 990410, 'Talk'
    UNION ALL SELECT 990410, 'Workshop'
    UNION ALL SELECT 990403, 'Workshop'
) AS akce
JOIN sjednocene_tagy ON sjednocene_tagy.nazev = akce.nazev
SQL,
            // the group organises from its own account, its speaker has no user (záměr)
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (990401, 'lektor-po-gc', 'Lektor', 'Pogc', 'lektor-po-gc@example.invalid'),
    (990402, 'novacek-po-gc', 'Nováček', 'Pogc', 'novacek-po-gc@example.invalid'),
    (990403, 'Skupina po GC', '', '', 'skupina-po-gc@example.invalid')
SQL,
            // organisers of both terms, the second only on the term that did not take place
            <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES (990401, 990401), (990402, 990402), (990410, 990403)
SQL,
            // what shows that a card was in the program: someone signed up (here on the other term of the first card),
            // or a state of the public program in the log; the card 990411 has neither
            <<<'SQL'
INSERT INTO akce_prihlaseni_log(id_akce, id_uzivatele, typ) VALUES (990402, 990402, 'prihlaseni')
SQL,
            <<<'SQL'
INSERT INTO akce_stavy_log(id_akce, id_stav, kdy)
SELECT id_akce, 2, '2026-06-01 12:00:00' FROM akce_seznam WHERE id_akce IN (990403, 990404, 990405, 990406, 990408, 990409, 990410, 990412, 990414)
SQL,
        ];
    }

    public static function setUpBeforeClass(): void
    {
        // before the transaction of the class, a trigger is a change of the structure and would commit it
        dbQuery(<<<'SQL'
            CREATE OR REPLACE TRIGGER archiv_prednasek_selhani_v_testu BEFORE INSERT ON archiv_prednasek FOR EACH ROW
            IF NEW.nazev = 'Selže při zápisu' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Zápis selhal v testu';
            END IF
            SQL);
        // marking the activity in @archiv_prednasek_selze_oznaceni fails once, as on a lock wait timeout
        dbQuery(<<<'SQL'
            CREATE OR REPLACE TRIGGER archiv_prednasek_oznaceni_v_testu BEFORE UPDATE ON archiv_prednasek_zpracovane_aktivity FOR EACH ROW
            IF NEW.id_akce = @archiv_prednasek_selze_oznaceni THEN
                SET @archiv_prednasek_selze_oznaceni = NULL;
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Označení selhalo v testu';
            END IF
            SQL);
        // with @archiv_prednasek_selze_zaznam_chyby = 1 the record of an error of a new card fails
        dbQuery(<<<'SQL'
            CREATE OR REPLACE TRIGGER archiv_prednasek_zaznam_chyby_v_testu BEFORE INSERT ON archiv_prednasek_zpracovane_aktivity FOR EACH ROW
            IF NEW.chyba IS NOT NULL AND @archiv_prednasek_selze_zaznam_chyby = 1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Záznam chyby selhal v testu';
            END IF
            SQL);
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        dbQuery(<<<'SQL'
            DROP TRIGGER IF EXISTS archiv_prednasek_selhani_v_testu
            SQL);
        dbQuery(<<<'SQL'
            DROP TRIGGER IF EXISTS archiv_prednasek_oznaceni_v_testu
            SQL);
        dbQuery(<<<'SQL'
            DROP TRIGGER IF EXISTS archiv_prednasek_zaznam_chyby_v_testu
            SQL);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $rok = self::ROK;
        // the migration already filled the archive of the test database
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_prednasejici
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_klicove_slovo
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_zpracovane_aktivity
            SQL);
        dbQuery(<<<SQL
            INSERT INTO archiv_prednasek(id_prednasky, nazev, rok, id_akce)
            VALUES
                (990401, 'Ručně spárovaná', {$rok}, 990405),
                (990402, 'Stejný název jako ruční', {$rok}, NULL),
                (990403, 'Spárovaná s hlavní instancí', {$rok}, 990413)
            SQL);
        // the speaker linked to the organiser from the spreadsheet keeps his name; the group has no user
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno, id_uzivatele)
            VALUES (990401, 'Občanské jméno z tabulky', 990401), (990402, 'Skupina po GC', NULL)
            SQL);
        // the job writes as on a strict server, only the partial fixtures above need the lenient mode
        static::enableStrictTransTables();
    }

    protected function tearDown(): void
    {
        static::disableStrictTransTables();
        dbQuery(<<<'SQL'
            SET @archiv_prednasek_selze_oznaceni = NULL, @archiv_prednasek_selze_zaznam_chyby = NULL
            SQL);
        parent::tearDown();
    }

    private static function poGc(int $letosniRocnik = self::ROK, string $ted = self::ROK . '-11-10 12:00'): DoplneniZProgramu
    {
        return new DoplneniZProgramu(
            $letosniRocnik,
            new \DateTimeImmutable($ted),
            new \DateTimeImmutable("{$letosniRocnik}-07-26 23:59"),
        );
    }

    /**
     * @return list<string> what the job logged
     */
    private static function dopln(DoplneniZProgramu $doplneni): array
    {
        $zpravy = [];
        $doplneni->doplnSkoncene(static function (string $zprava) use (&$zpravy): void {
            $zpravy[] = $zprava;
        });

        return $zpravy;
    }

    /**
     * @return array<string, array{bool, int|null}> title => [hidden, activity]
     */
    private static function prednaskyRoku(): array
    {
        $prednasky = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT nazev, skryta, id_akce FROM archiv_prednasek WHERE rok = $0 ORDER BY nazev
            SQL, [
            0 => self::ROK,
        ]) as $radek) {
            $prednasky[$radek['nazev']] = [(bool) $radek['skryta'], isset($radek['id_akce']) ? (int) $radek['id_akce'] : null];
        }

        return $prednasky;
    }

    public function testSkoncenyJeRocnikPoKonciGameconuVTomtoRoce(): void
    {
        $predKoncem = new DoplneniZProgramu(self::ROK, new \DateTimeImmutable(self::ROK . '-07-25 12:00'), new \DateTimeImmutable(self::ROK . '-07-26 23:59'));
        self::assertSame([], $predKoncem->getSkonceneRocniky(), 'Během GameConu ještě ne');
        self::assertSame([self::ROK], self::poGc()->getSkonceneRocniky());

        // after New Year the current year is the next one, the finished year still counts
        $poNovemRoce = new DoplneniZProgramu(self::ROK + 1, new \DateTimeImmutable((self::ROK + 1) . '-01-15 12:00'), new \DateTimeImmutable((self::ROK + 1) . '-07-25 23:59'));
        self::assertSame([self::ROK], $poNovemRoce->getSkonceneRocniky());

        // after the rollover GC_BEZI_DO is still the past one until someone changes it: the end of the new year is
        // computed, it has not come yet…
        $poPreklopeni = new DoplneniZProgramu(self::ROK + 1, new \DateTimeImmutable(self::ROK . '-12-15 12:00'), new \DateTimeImmutable(self::ROK . '-07-26 23:59'));
        self::assertSame([self::ROK], $poPreklopeni->getSkonceneRocniky());
        // …and after the GameCon of the new year it has, even when nobody changed the setting
        $poDalsimGcBezZmenyNastaveni = new DoplneniZProgramu(self::ROK + 1, new \DateTimeImmutable((self::ROK + 1) . '-08-15 12:00'), new \DateTimeImmutable(self::ROK . '-07-26 23:59'));
        self::assertSame([self::ROK, self::ROK + 1], $poDalsimGcBezZmenyNastaveni->getSkonceneRocniky());

        // the end of the next year set before the rollover does not hold the current one back
        $predPreklopenim = new DoplneniZProgramu(self::ROK, new \DateTimeImmutable(self::ROK . '-11-10 12:00'), new \DateTimeImmutable((self::ROK + 1) . '-07-25 23:59'));
        self::assertSame([self::ROK], $predPreklopenim->getSkonceneRocniky());

        // years before the first one are decided by people
        self::assertSame([], self::poGc(self::ROK - 1)->getSkonceneRocniky());
    }

    public function testBehemGameconuNicNezalozi(): void
    {
        self::assertSame([], self::dopln(self::poGc(ted: self::ROK . '-07-25 12:00')));
        self::assertSame(
            ['Ručně spárovaná', 'Spárovaná s hlavní instancí', 'Stejný název jako ruční'],
            array_keys(self::prednaskyRoku()),
        );
        self::assertSame('0', (string) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_zpracovane_aktivity
            SQL), 'Ani karty nezaznamená, po GC je musí zpracovat');
    }

    public function testZaloziKartyKtereProbehlyAUzVArchivuNejsou(): void
    {
        $zpravy = self::dopln(self::poGc());

        // by Czech collation of the database: l before ř
        self::assertSame([
            'Nikdy nezveřejněná' => [true, 990411],
            'Placený workshop'   => [true, 990403],
            // card with two terms, one took place; the title is unified
            'Přednáška se dvěma termíny – druhý díl' => [false, 990401],
            'Ručně spárovaná'                        => [false, 990405],
            // the term is a lecture, the main activity is not: the card is in the archive by its main activity
            'Spárovaná s hlavní instancí' => [false, 990413],
            'Stejný název jako ruční'     => [false, null],
            'Workshop i přednáška'        => [false, 990410],
            'Zahájení GameConu'           => [true, 990412],
            'Zrušená – !ZRUŠENO!'         => [true, 990409],
            'Živé hraní na pódiu'         => [true, 990408],
        ], self::prednaskyRoku());
        self::assertContains(
            'Archiv přednášek ' . self::ROK . ': „Stejný název jako ruční“ (aktivita 990406) přeskočena, přednáška toho jména v archivu už je',
            $zpravy,
        );
        self::assertContains(
            'Archiv přednášek ' . self::ROK . ': „Nikdy nezveřejněná“ (aktivita 990411) přidána, skrytá, v programu asi nebyla, nikdo se na ni nepřihlásil',
            $zpravy,
        );
    }

    /**
     * A speaker linked to the organiser comes first, then one of the same name without a user, a new one gets the name on the web
     * of the organiser (his nick by default).
     */
    public function testPrednasejiciJsouVedouciVsechTerminuANapojeniSePouzijeZnovu(): void
    {
        self::dopln(self::poGc());

        $prednasejiciPrednasky = static fn (int $idAkce) => array_map(
            static fn (array $radek) => [$radek['jmeno'], isset($radek['id_uzivatele']) ? (int) $radek['id_uzivatele'] : null],
            dbFetchAll(<<<'SQL'
                SELECT archiv_prednasek_prednasejici.jmeno, archiv_prednasek_prednasejici.id_uzivatele
                FROM archiv_prednasek_prednaska_prednasejici
                JOIN archiv_prednasek_prednasejici
                    ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
                WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = (SELECT id_prednasky FROM archiv_prednasek WHERE id_akce = $0)
                ORDER BY archiv_prednasek_prednaska_prednasejici.poradi
                SQL, [
                0 => $idAkce,
            ]),
        );
        self::assertSame([['Občanské jméno z tabulky', 990401], ['novacek-po-gc', 990402]], $prednasejiciPrednasky(990401));
        self::assertSame([['Skupina po GC', null]], $prednasejiciPrednasky(990410), 'Skupina zůstane bez uživatele');
        self::assertSame(1, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_prednasejici WHERE jmeno = 'Skupina po GC'
            SQL), 'Přednášející stejného jména je tentýž');
    }

    /**
     * A speaker of the same name linked to another user is a namesake: the lecture goes to a new speaker of the
     * organiser, not to him.
     */
    public function testJmenovecNapojenyNaJinehoUzivateleNeniTentyz(): void
    {
        static::disableStrictTransTables();
        dbQuery(<<<'SQL'
            INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
            VALUES (990404, 'jmenovec-po-gc', 'Jmenovec', 'Pogc', 'jmenovec-po-gc@example.invalid')
            SQL);
        static::enableStrictTransTables();
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno, id_uzivatele) VALUES (990403, 'novacek-po-gc', 990404)
            SQL);

        self::dopln(self::poGc());

        // the organiser 990402 is „novacek-po-gc“ on the web, as is the namesake of the user 990404
        self::assertSame(
            [['Občanské jméno z tabulky', 990401], ['novacek-po-gc', 990402]],
            array_map(
                static fn (array $radek) => [$radek['jmeno'], (int) $radek['id_uzivatele']],
                dbFetchAll(<<<'SQL'
                    SELECT archiv_prednasek_prednasejici.jmeno, archiv_prednasek_prednasejici.id_uzivatele
                    FROM archiv_prednasek_prednaska_prednasejici
                    JOIN archiv_prednasek_prednasejici
                        ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
                    WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = (SELECT id_prednasky FROM archiv_prednasek WHERE id_akce = 990401)
                    ORDER BY archiv_prednasek_prednaska_prednasejici.poradi
                    SQL),
            ),
        );
    }

    /**
     * A term of a decided card moved to a new card: the new card is decided by its main activity, the moved term
     * does not hold it back (it would be rolled back every hour without a word).
     */
    public function testKartaSTerminemJineRozhodnuteKartySeDoplni(): void
    {
        self::dopln(self::poGc());
        $zpracovaniPresunuteho = dbOneLine(<<<'SQL'
            SELECT zpracovano, chyba FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990402
            SQL);
        self::assertNotNull($zpracovaniPresunuteho);

        // the term 990402 of the decided card 990401 moves under the new card 990404, which takes place
        dbQuery(<<<'SQL'
            INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES (990404, 990404)
            SQL);
        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET patri_pod = 990404, stav = IF(id_akce = 990404, 7, stav) WHERE id_akce IN (990402, 990404)
            SQL);

        self::assertSame(
            ['Archiv přednášek ' . self::ROK . ': „Proběhne později“ (aktivita 990404) přidána'],
            self::dopln(self::poGc(ted: self::ROK . '-11-10 13:00')),
        );
        self::assertSame([false, 990404], self::prednaskyRoku()['Proběhne později']);
        self::assertSame($zpracovaniPresunuteho, dbOneLine(<<<'SQL'
            SELECT zpracovano, chyba FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990402
            SQL), 'Přesunutý termín zůstane zpracovaný s původní kartou');
        self::assertSame([], self::dopln(self::poGc(ted: self::ROK . '-11-10 14:00')), 'Další běh nic');
    }

    public function testRucniUpravySkrytiAZruseniSparovaniDalsiBehNechaByt(): void
    {
        self::dopln(self::poGc());
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET nazev = 'Přejmenovaná adminem', skryta = 1, id_akce = NULL WHERE id_akce = 990401
            SQL);
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET id_akce = NULL WHERE id_akce = 990405
            SQL);
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET skryta = 0 WHERE id_akce = 990403
            SQL);

        self::assertSame([], self::dopln(self::poGc()), 'Druhý běh nic nedělá');

        $prednasky = self::prednaskyRoku();
        self::assertSame([true, null], $prednasky['Přejmenovaná adminem']);
        self::assertArrayNotHasKey('Přednáška se dvěma termíny – druhý díl', $prednasky, 'Zrušené spárování nevrátí kartu');
        self::assertArrayNotHasKey('Už v archivu', $prednasky, 'Ani u karty, kterou do archivu přidal admin');
        self::assertSame([false, 990403], $prednasky['Placený workshop'], 'Vrácená na web zůstane');
    }

    public function testKartaKteraProbehnePozdejiPrijdeSDalsimBehem(): void
    {
        self::dopln(self::poGc());
        self::assertArrayNotHasKey('Proběhne později', self::prednaskyRoku());

        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET stav = 7 WHERE id_akce = 990404
            SQL);

        self::assertSame(
            ['Archiv přednášek ' . self::ROK . ': „Proběhne později“ (aktivita 990404) přidána'],
            self::dopln(self::poGc()),
        );
    }

    public function testKartaKteraSelzeNezastaviOstatniAZkusiSeZnovuAzZaDen(): void
    {
        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET nazev_akce = $0 WHERE id_akce = 990410
            SQL, [
            0 => self::NAZEV_SELZE,
        ]);
        try {
            self::dopln(self::poGc());
            self::fail('Chyba karty se má ohlásit');
        } catch (\RuntimeException $chyba) {
            self::assertStringContainsString('„' . self::NAZEV_SELZE . '“ (aktivita 990410) se nepodařilo založit, další pokus za den', $chyba->getMessage());
            self::assertStringContainsString('Zápis selhal v testu', $chyba->getPrevious()?->getMessage() ?? '');
        }
        self::assertArrayHasKey('Přednáška se dvěma termíny – druhý díl', self::prednaskyRoku(), 'Ostatní karty jsou v archivu');
        self::assertArrayNotHasKey(self::NAZEV_SELZE, self::prednaskyRoku());
        self::assertSame('Zápis selhal v testu', dbOneCol(<<<'SQL'
            SELECT chyba FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990410
            SQL));

        self::assertSame([], self::dopln(self::poGc(ted: self::ROK . '-11-11 11:00')), 'Do druhého dne se nezkouší, žádný mail každou hodinu');

        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET nazev_akce = 'Workshop i přednáška' WHERE id_akce = 990410
            SQL);
        self::assertSame(
            ['Archiv přednášek ' . self::ROK . ': „Workshop i přednáška“ (aktivita 990410) přidána'],
            self::dopln(self::poGc(ted: self::ROK . '-11-11 13:00')),
        );
        self::assertNull(dbOneCol(<<<'SQL'
            SELECT chyba FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990410
            SQL));
    }

    /**
     * Another run (the hourly cron and a manual ?job=, or a slow run and the next one) processes the cards that the
     * first one has already loaded; the first one then leaves them alone.
     */
    public function testKartyKtereMezitimZpracovalJinyBehNecha(): void
    {
        $zpravy = [];
        $zpravyDruhehoBehu = null;
        self::poGc()->doplnSkoncene(static function (string $zprava) use (&$zpravy, &$zpravyDruhehoBehu): void {
            $zpravy[] = $zprava;
            $zpravyDruhehoBehu ??= self::dopln(self::poGc(ted: self::ROK . '-11-10 12:30'));
        });

        self::assertSame(
            ['Archiv přednášek ' . self::ROK . ': „Přednáška se dvěma termíny – druhý díl“ (aktivita 990401) přidána'],
            $zpravy,
            'Po první kartě první běh nic nezaloží ani nepřeskočí, ostatní zpracoval druhý',
        );
        self::assertContains('Archiv přednášek ' . self::ROK . ': „Workshop i přednáška“ (aktivita 990410) přidána', $zpravyDruhehoBehu ?? []);
        self::assertSame('0', (string) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_zpracovane_aktivity
            WHERE id_akce NOT IN (990401, 990402) AND (zpracovano != '2026-11-10 12:30:00' OR chyba IS NOT NULL)
            SQL), 'Karty druhého běhu zůstanou jeho');
    }

    /**
     * A run that fails on a card that another run processed in the meantime (a lock wait timeout) does not make it
     * failed: a day later it would be processed again.
     */
    public function testChybaKartyKterouMezitimZpracovalJinyBehJejiZaznamNeprepise(): void
    {
        $druhyBehProbehl = false;
        try {
            self::poGc()->doplnSkoncene(static function () use (&$druhyBehProbehl): void {
                if ($druhyBehProbehl) {
                    return;
                }
                $druhyBehProbehl = true;
                self::dopln(self::poGc(ted: self::ROK . '-11-10 12:30'));
                dbQuery(<<<'SQL'
                    SET @archiv_prednasek_selze_oznaceni = 990410
                    SQL);
            });
            self::fail('Chyba karty se má ohlásit');
        } catch (\RuntimeException $chyba) {
            self::assertStringContainsString('(aktivita 990410) se nepodařilo založit, další pokus za den: Označení selhalo v testu', $chyba->getMessage());
        }
        self::assertSame(
            [
                'zpracovano' => '2026-11-10 12:30:00',
                'chyba'      => null,
            ],
            dbOneLine(<<<'SQL'
                SELECT zpracovano, chyba FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990410
                SQL),
            'Karta zůstane zpracovaná druhým během',
        );
    }

    public function testKdyzSeChybaNepodariZaznamenatOstatniKartyPrijdouNaRadu(): void
    {
        dbQuery(<<<'SQL'
            UPDATE akce_seznam SET nazev_akce = $0 WHERE id_akce = 990410
            SQL, [
            0 => self::NAZEV_SELZE,
        ]);
        dbQuery(<<<'SQL'
            SET @archiv_prednasek_selze_zaznam_chyby = 1
            SQL);
        try {
            self::dopln(self::poGc());
            self::fail('Chyba karty se má ohlásit');
        } catch (\RuntimeException $chyba) {
            self::assertStringContainsString(
                '„' . self::NAZEV_SELZE . '“ (aktivita 990410) se nepodařilo založit, chybu se nepodařilo zaznamenat (Záznam chyby selhal v testu), další pokus s příštím během: Zápis selhal v testu',
                $chyba->getMessage(),
            );
            self::assertStringContainsString('Zápis selhal v testu', $chyba->getPrevious()?->getMessage() ?? '');
        }
        self::assertArrayHasKey('Zahájení GameConu', self::prednaskyRoku(), 'Karty po ní jsou v archivu');
        self::assertSame('0', (string) dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM archiv_prednasek_zpracovane_aktivity WHERE id_akce = 990410
            SQL), 'Bez záznamu ji zkusí příští běh');
    }

    /**
     * @return array<string, array{string, bool, list<string>, bool}>
     */
    public static function provideKarty(): array
    {
        return [
            'přednáška'              => ['Tvorba světů', false, ['Talk', 'RPG'], true],
            'placená'                => ['Workshop pro PJe', true, ['Workshop'], false],
            'workshop bez přednášky' => ['Malování', false, ['Workshop'], false],
            'workshop s přednáškou'  => ['Tvorba RPG světů', false, ['Workshop', 'Talk'], true],
            'panel s workshopem'     => ['Panel', false, ['Workshop', 'Panelová diskuse'], true],
            'živé hraní'             => ['Veřejná hra', false, ['Živé hraní', 'RPG'], false],
            'zrušená'                => ['Zbraně Warhammeru – !zrušeno!', false, ['Talk'], false],
            'zrušená jinak'          => ['Zbraně Warhammeru – ZRUŠENÁ', false, ['Talk'], false],
            'zrušené'                => ['Zbraně Warhammeru – !ZRUŠENÉ!', false, ['Talk'], false],
            'zrušenou'               => ['Zbraně Warhammeru (zrušenou přednášku nahradí panel)', false, ['Talk'], false],
            'zrušení v názvu'        => ['Zrušení nevolnictví v RPG', false, ['Talk'], true],
            'zrušením v názvu'       => ['Co se stalo se zrušením ceny', false, ['Talk'], true],
            'zahájení'               => ['Slavnostní zahájení', false, [], false],
            'zahájení ročníku'       => ['Zahájení GC 2014', false, [], false],
            'zahájení Parconu'       => ['Zahájení Parconu', false, [], false],
            'zakončení'              => ['Zakončení GameConu 2013', false, [], false],
            'zahájení kampaně'       => ['Zahájení kampaně v RPG', false, ['Talk'], true],
            'vyhlášení'              => ['Vyhlašování výsledků Ceny Karla Čapka', false, [], false],
            'vyhlášení soutěže'      => ['Vyhlášení literární soutěže Zlatá zebra', false, [], false],
            'soutěž'                 => ['Literární soutěž Zlatá zebra', false, [], false],
            'soutěž v závorce'       => ['3... 2... 1... Postřeh! (soutěž)', false, [], false],
            'soutěžní'               => ['Jak navrhnout soutěžní deskovku', false, ['Talk'], true],
            'soutěživost'            => ['Soutěživost u herního stolu', false, ['Talk'], true],
            'oběd'                   => ['Oběd s organizátory', false, [], false],
        ];
    }

    /**
     * @param list<string> $stitky
     *
     * @dataProvider provideKarty
     */
    public function testPoznaKartuKteraJePrednaska(string $nazev, bool $placena, array $stitky, bool $jePrednaska): void
    {
        self::assertSame($jePrednaska, DoplneniZProgramu::isPrednaska($nazev, $placena, $stitky));
    }
}
