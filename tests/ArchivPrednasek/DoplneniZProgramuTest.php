<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\DoplneniZProgramu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Lectures of a finished GameCon go into the archive once, without touching what is there.
 */
class DoplneniZProgramuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private const int ROK = DoplneniZProgramu::PRVNI_ROCNIK;

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
    (990404, 'Neproběhlá přednáška', 'neprobehla-po-gc', 3, {$rok}, 5, '', 0, 40, 0, 0),
    (990405, 'Už v archivu', 'uz-v-archivu-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990406, 'Stejný název jako ruční', 'stejny-nazev-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990407, 'RPG hra', 'rpg-po-gc', 4, {$rok}, 7, '', 0, 5, 0, 0),
    (990408, 'Živé hraní na pódiu', 'zive-hrani-po-gc', 3, {$rok}, 7, '', 0, 60, 0, 0),
    (990409, 'Zrušená - !ZRUŠENO!', 'zrusena-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0),
    (990410, 'Workshop i přednáška', 'workshop-talk-po-gc', 3, {$rok}, 7, '', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES (990401, 990401)
SQL,
            <<<'SQL'
UPDATE akce_seznam SET patri_pod = 990401 WHERE id_akce = 990401
SQL,
            // the other term did not take place; one finished term is enough
            <<<SQL
INSERT INTO akce_seznam(id_akce, patri_pod, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES (990402, 990401, 'Přednáška se dvěma termíny - druhý díl', '', 3, {$rok}, 2, '', 0, 40, 0, 0)
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
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (990401, 'lektor-po-gc', 'Lektor', 'Pogc', 'lektor-po-gc@example.invalid'),
    (990402, 'novacek-po-gc', 'Nováček', 'Pogc', 'novacek-po-gc@example.invalid')
SQL,
            // organisers of both terms, the second only on the term that did not take place
            <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES (990401, 990401), (990402, 990402)
SQL,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $rok = self::ROK;
        // the migration already filled the archive of the test database
        dbQuery('DELETE FROM archiv_prednasek');
        dbQuery('DELETE FROM archiv_prednasek_prednasejici');
        dbQuery('DELETE FROM archiv_prednasek_klicove_slovo');
        dbQuery('DELETE FROM archiv_prednasek_doplnene_rocniky');
        dbQuery(<<<SQL
            INSERT INTO archiv_prednasek(id_prednasky, nazev, rok, id_akce)
            VALUES (990401, 'Ručně spárovaná', {$rok}, 990405), (990402, 'Stejný název jako ruční', {$rok}, NULL)
            SQL);
        // the speaker linked to the organiser from the spreadsheet keeps his name
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno, id_uzivatele)
            VALUES (990401, 'Občanské jméno z tabulky', 990401)
            SQL);
    }

    private static function poGc(int $letosniRocnik = self::ROK): DoplneniZProgramu
    {
        return new DoplneniZProgramu(
            $letosniRocnik,
            new \DateTimeImmutable("{$letosniRocnik}-11-10 12:00"),
            new \DateTimeImmutable("{$letosniRocnik}-07-26 23:59"),
        );
    }

    /**
     * @return array<string, array{bool, int|null}> title => [hidden, activity]
     */
    private static function prednaskyRoku(): array
    {
        $prednasky = [];
        foreach (dbFetchAll('SELECT nazev, skryta, id_akce FROM archiv_prednasek WHERE rok = $0 ORDER BY nazev', [
            0 => self::ROK,
        ]) as $radek) {
            $prednasky[$radek['nazev']] = [(bool) $radek['skryta'], isset($radek['id_akce']) ? (int) $radek['id_akce'] : null];
        }

        return $prednasky;
    }

    public function testDoplniRocnikJenJednouAKdyzGameconSkoncil(): void
    {
        $predKoncem = new DoplneniZProgramu(self::ROK, new \DateTimeImmutable(self::ROK . '-07-25 12:00'), new \DateTimeImmutable(self::ROK . '-07-26 23:59'));
        self::assertSame([], $predKoncem->rocnikyKDoplneni(), 'Během GameConu ještě ne');
        self::assertSame([self::ROK], self::poGc()->rocnikyKDoplneni());

        // after New Year the current year is the next one, the finished year still gets in
        $poNovemRoce = new DoplneniZProgramu(self::ROK + 1, new \DateTimeImmutable((self::ROK + 1) . '-01-15 12:00'), new \DateTimeImmutable((self::ROK + 1) . '-07-25 23:59'));
        self::assertSame([self::ROK], $poNovemRoce->rocnikyKDoplneni());

        // years before the first one are decided by people
        self::assertSame([], self::poGc(self::ROK - 1)->rocnikyKDoplneni());

        self::poGc()->doplnSkoncene();
        self::assertSame([], self::poGc()->rocnikyKDoplneni());
        self::assertSame([], self::poGc()->doplnSkoncene(), 'Druhý běh nic nedělá');
    }

    public function testZaloziKartyKtereProbehlyAUzVArchivuNejsou(): void
    {
        $zpravy = self::poGc()->doplnSkoncene();

        // by Czech collation of the database: l before ř
        self::assertSame([
            'Placený workshop' => [true, 990403],
            // card with two terms, one took place; the title is unified
            'Přednáška se dvěma termíny – druhý díl' => [false, 990401],
            'Ručně spárovaná'                        => [false, 990405],
            'Stejný název jako ruční'                => [false, null],
            'Workshop i přednáška'                   => [false, 990410],
            'Zrušená – !ZRUŠENO!'                    => [true, 990409],
            'Živé hraní na pódiu'                    => [true, 990408],
        ], self::prednaskyRoku());
        self::assertContains(
            'Archiv přednášek ' . self::ROK . ': „Stejný název jako ruční“ (aktivita 990406) přeskočena, přednáška toho jména v archivu už je',
            $zpravy,
        );
    }

    /**
     * A new speaker gets the name on the web of the organiser (his nick by default).
     */
    public function testPrednasejiciJsouVedouciVsechTerminuANapojeniSePouzijeZnovu(): void
    {
        self::poGc()->doplnSkoncene();

        self::assertSame(
            [['Občanské jméno z tabulky', '990401'], ['novacek-po-gc', '990402']],
            array_map(static fn (array $radek) => [$radek['jmeno'], $radek['id_uzivatele']], dbFetchAll(<<<'SQL'
                SELECT archiv_prednasek_prednasejici.jmeno, archiv_prednasek_prednasejici.id_uzivatele
                FROM archiv_prednasek_prednaska_prednasejici
                JOIN archiv_prednasek_prednasejici
                    ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
                WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = (SELECT id_prednasky FROM archiv_prednasek WHERE id_akce = 990401)
                ORDER BY archiv_prednasek_prednaska_prednasejici.poradi
                SQL)),
        );
    }

    public function testRucniUpravySkrytiAZruseniSparovaniDalsiBehNechaByt(): void
    {
        self::poGc()->doplnSkoncene();
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET nazev = 'Přejmenovaná adminem', skryta = 1, id_akce = NULL WHERE id_akce = 990401
            SQL);
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET skryta = 0 WHERE id_akce = 990403
            SQL);

        self::poGc()->doplnSkoncene();

        $prednasky = self::prednaskyRoku();
        self::assertSame([true, null], $prednasky['Přejmenovaná adminem']);
        self::assertArrayNotHasKey('Přednáška se dvěma termíny – druhý díl', $prednasky, 'Zrušené spárování nevrátí kartu');
        self::assertSame([false, 990403], $prednasky['Placený workshop'], 'Vrácená na web zůstane');
    }

    /**
     * @return array<string, array{string, bool, list<string>, bool}>
     */
    public static function provideKarty(): array
    {
        return [
            'přednáška'              => ['Tvorba světů', false, ['Talk', 'RPG'], false],
            'placená'                => ['Workshop pro PJe', true, ['Workshop'], true],
            'workshop bez přednášky' => ['Malování', false, ['Workshop'], true],
            'workshop s přednáškou'  => ['Tvorba RPG světů', false, ['Workshop', 'Talk'], false],
            'panel s workshopem'     => ['Panel', false, ['Workshop', 'Panelová diskuse'], false],
            'živé hraní'             => ['Veřejná hra', false, ['Živé hraní', 'RPG'], true],
            'zrušená'                => ['Zbraně Warhammeru – !zrušeno!', false, ['Talk'], true],
        ];
    }

    /**
     * @param list<string> $stitky
     *
     * @dataProvider provideKarty
     */
    public function testPoznaKartuKteraNeniPrednaska(string $nazev, bool $placena, array $stitky, bool $neniPrednaska): void
    {
        self::assertSame($neniPrednaska, DoplneniZProgramu::nevypadaNaPrednasku($nazev, $placena, $stitky));
    }
}
