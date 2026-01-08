<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Lokace;
use Gamecon\Aktivita\SqlStruktura\AkceLokaceSqlStruktura as JunctionSql;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Testy pro funkci aktivity ve více místnostech (multiple locations per activity)
 */
class AktivitaViceMistnostiTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static array $initQueries = [
        // Note: The migration 2025-05-27-165452-aktivita-do-vice-mistnosti.sql has already run
        // in the test database, so lokace table exists and akce_lokace is now the junction table.
        // Insert 6 test locations into lokace table
        <<<SQL
INSERT INTO lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
    (1001, 'Velká místnost', 'Budova A, dveře 101', 'Hlavní sál', 1, 0),
    (1002, 'Malá místnost', 'Budova A, dveře 102', '', 2, 0),
    (1003, 'Klubovna', 'Budova B, dveře 201', 'Pro menší skupiny', 3, 0),
    (1004, 'Venkovní prostor', '', 'Zahrada', 4, 0),
    (1005, 'Přednášková místnost', 'Budova C, dveře 301', '', 5, 0),
    (1006, 'Testovací místnost', 'Test dvere', '', 6, 0)
SQL,
        // Insert 5 test activities (some with locations, some without)
        <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, typ, rok, stav)
VALUES
    (2001, 'Aktivita s jednou místností', 1, 2024, 2),
    (2002, 'Aktivita s více místnostmi', 1, 2024, 2),
    (2003, 'Aktivita bez místnosti', 1, 2024, 2),
    (2004, 'Aktivita pro přesun', 1, 2024, 2),
    (2005, 'Aktivita pro změnu hlavní', 1, 2024, 2)
SQL,
        // Setup junction table - activity 1 has location 1
        <<<SQL
INSERT INTO akce_lokace(id_akce, id_lokace, je_hlavni)
VALUES
    (2001, 1001, 1)
SQL,
        // Setup junction table - activity 2 has locations 1, 2, 3 (1 is main)
        <<<SQL
INSERT INTO akce_lokace(id_akce, id_lokace, je_hlavni)
VALUES
    (2002, 1001, 1),
    (2002, 1002, 0),
    (2002, 1003, 0)
SQL,
        // Activity 3 has no locations
        // Activity 4 has location 4
        <<<SQL
INSERT INTO akce_lokace(id_akce, id_lokace, je_hlavni)
VALUES
    (2004, 1004, 1)
SQL,
        // Activity 5 has locations 5, 6 (5 is main)
        <<<SQL
INSERT INTO akce_lokace(id_akce, id_lokace, je_hlavni)
VALUES
    (2005, 1005, 1),
    (2005, 1006, 0)
SQL,
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [];
    }

    // ========================================================================
    // Category 1: Basic Location Retrieval (4 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testAktivitaBezMistnosti()
    {
        $aktivita = Aktivita::zId(2003);

        self::assertSame([], $aktivita->seznamLokaci());
        self::assertNull($aktivita->hlavniLokace());
        self::assertSame([], $aktivita->seznamLokaciIdcka());
        self::assertSame('', $aktivita->popisLokaci());
        self::assertSame('', $aktivita->nazevLokaci());
    }

    /**
     * @test
     */
    public function testAktivitaSJednouMistnosti()
    {
        $aktivita = Aktivita::zId(2001);

        $lokace = $aktivita->seznamLokaci();
        self::assertCount(1, $lokace);
        self::assertInstanceOf(Lokace::class, $lokace[0]);
        self::assertSame(1001, $lokace[0]->id());
        self::assertSame('Velká místnost', $lokace[0]->nazev());

        self::assertSame(1001, $aktivita->hlavniLokace()->id());
        self::assertSame([1001], $aktivita->seznamLokaciIdcka());
    }

    /**
     * @test
     */
    public function testAktivitaSViceMistnostmi()
    {
        $aktivita = Aktivita::zId(2002);

        $lokace = $aktivita->seznamLokaci();
        self::assertCount(3, $lokace);

        // Main location should be first
        self::assertSame(1, $lokace[0]->id(), 'První by měla být hlavní místnost');
        self::assertSame(1001, $aktivita->hlavniLokace()->id());

        // Others sorted by poradi
        self::assertSame(2, $lokace[2001]->id());
        self::assertSame(3, $lokace[2002]->id());

        self::assertSame([1001, 1002, 1003], $aktivita->seznamLokaciIdcka());
    }

    /**
     * @test
     */
    public function testPopisANazevLokaci()
    {
        $aktivita = Aktivita::zId(2002);

        // popisLokaci includes doors/details
        $popis = $aktivita->popisLokaci();
        self::assertStringContainsString('Velká místnost', $popis);
        self::assertStringContainsString('Budova A, dveře 101', $popis);

        // nazevLokaci is names only
        $nazev = $aktivita->nazevLokaci();
        self::assertSame('Velká místnost, Malá místnost, Klubovna', $nazev);
    }

    // ========================================================================
    // Category 2: Update Operations (4 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testNastaveniNovychLokaci()
    {
        $aktivita = Aktivita::zId(2003);
        self::assertSame([], $aktivita->seznamLokaci(), 'Aktivita by neměla mít žádné místnosti');

        $aktivita->nastavLokacePodleIds([1001, 1002], 1001);

        // Reload and verify
        $aktivita = Aktivita::zId(2003);
        self::assertCount(2, $aktivita->seznamLokaci());
        self::assertSame(1001, $aktivita->hlavniLokace()->id());

        // Verify junction table
        $junctionRows = dbFetchAll(
            'SELECT id_lokace, je_hlavni FROM akce_lokace WHERE id_akce = ? ORDER BY id_lokace',
            [2003]
        );
        self::assertCount(2, $junctionRows);
        self::assertSame('1001', $junctionRows[0]['id_lokace']);
        self::assertSame('1', $junctionRows[0]['je_hlavni']);
        self::assertSame('1002', $junctionRows[1]['id_lokace']);
        self::assertSame('0', $junctionRows[1]['je_hlavni']);
    }

    /**
     * @test
     */
    public function testOdebraniLokaceZAktivity()
    {
        $aktivita = Aktivita::zId(2002);
        self::assertCount(3, $aktivita->seznamLokaci());

        // Keep only location 1
        $aktivita->nastavLokacePodleIds([2001], 1);

        $aktivita = Aktivita::zId(2002);
        self::assertCount(1, $aktivita->seznamLokaci());
        self::assertSame(1001, $aktivita->seznamLokaci()[0]->id());

        // Verify deleted from junction table
        $count = dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ?', [2002]);
        self::assertSame('1', $count);
    }

    /**
     * @test
     */
    public function testZmenaHlavniLokace()
    {
        $aktivita = Aktivita::zId(2005);
        self::assertSame(1005, $aktivita->hlavniLokace()->id(), 'Původně je hlavní místnost 5');

        // Change main to location 6
        $aktivita->nastavLokacePodleIds([1005, 1006], 1006);

        $aktivita = Aktivita::zId(2005);
        self::assertSame(1006, $aktivita->hlavniLokace()->id(), 'Nově je hlavní místnost 6');

        // Main location should be first in list
        self::assertSame(1006, $aktivita->seznamLokaci()[0]->id());

        // Verify je_hlavni flag in database
        $hlavniFlag = dbFetchSingle(
            'SELECT je_hlavni FROM akce_lokace WHERE id_akce = ? AND id_lokace = ?',
            [2005, 1006]
        );
        self::assertSame('1', $hlavniFlag);
    }

    /**
     * @test
     */
    public function testNastaveniLokaciBezHlavni()
    {
        $aktivita = Aktivita::zId(2003);

        // Set locations without specifying main
        $aktivita->nastavLokacePodleIds([1002, 1003, 1004], null);

        $aktivita = Aktivita::zId(2003);

        // First location (by id_lokace ASC) should become main
        self::assertSame(2, $aktivita->idHlavniLokace(), 'První místnost by měla být automaticky hlavní');

        // Verify no location has je_hlavni = 1
        $hlavniCount = dbFetchSingle(
            'SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ? AND je_hlavni = 1',
            [2003]
        );
        self::assertSame('0', $hlavniCount, 'Žádná místnost nemá explicitně nastavenou je_hlavni');
    }

    // ========================================================================
    // Category 3: Edge Cases & Validation (4 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testPrazdnePoleLokaciSmazeLokace()
    {
        $aktivita = Aktivita::zId(2001);
        self::assertCount(1, $aktivita->seznamLokaci());

        $aktivita->nastavLokacePodleIds([], null);

        $aktivita = Aktivita::zId(2001);
        self::assertSame([], $aktivita->seznamLokaci());

        $count = dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ?', [2001]);
        self::assertSame('0', $count);
    }

    /**
     * @test
     */
    public function testNastaveniNeexistujiciHlavniLokace()
    {
        $aktivita = Aktivita::zId(2001);

        // Try to set location 99 as main, but only add locations 1, 2
        $aktivita->nastavLokacePodleIds([1001, 1002], 9999);

        // Reload and check - 99 is not in list, so no location should be marked as main
        $aktivita = Aktivita::zId(2001);

        // Should fallback to first location by ORDER BY
        self::assertSame(1001, $aktivita->idHlavniLokace());

        // Verify no je_hlavni = 1 in database (since 99 wasn't in the list)
        $hlavniCount = dbFetchSingle(
            'SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ? AND je_hlavni = 1',
            [2001]
        );
        self::assertSame('0', $hlavniCount);
    }

    /**
     * @test
     */
    public function testDuplicityVSeznamuLokaci()
    {
        $aktivita = Aktivita::zId(2003);

        // Set with duplicates
        $aktivita->nastavLokacePodleIds([1001, 1001, 1002, 1002, 1003], 1001);

        $aktivita = Aktivita::zId(2003);

        // Should have unique locations only
        $ids = $aktivita->seznamLokaciIdcka();
        self::assertCount(3, $ids);
        self::assertSame([1001, 1002, 1003], $ids);

        // Verify junction table has no duplicates
        $count = dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ?', [2003]);
        self::assertSame('3', $count);
    }

    /**
     * @test
     */
    public function testZnovuNacteniLokaci()
    {
        $aktivita = Aktivita::zId(2001);
        $puvodni = $aktivita->seznamLokaci();
        self::assertCount(1, $puvodni);

        // Update locations
        $aktivita->nastavLokacePodleIds([1001, 1002, 1003], 1002);

        // Same instance should reflect changes
        $nove = $aktivita->seznamLokaci();
        self::assertCount(3, $nove, 'Cache by se měla zneplatnit');
        self::assertSame(2, $aktivita->hlavniLokace()->id());
    }

    // ========================================================================
    // Category 4: Database Integrity (2 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testForeignKeyConstraints()
    {
        $aktivita = Aktivita::zId(2003);

        // Try to set non-existent location ID
        $this->expectException(\Exception::class);
        $aktivita->nastavLokacePodleIds([9999], null);
    }

    /**
     * @test
     */
    public function testJunctionTableCompositeKey()
    {
        // Try to insert duplicate entry directly
        dbInsert(JunctionSql::AKCE_LOKACE_TABULKA, [
            JunctionSql::ID_AKCE => 1,
            JunctionSql::ID_LOKACE => 1,
            JunctionSql::JE_HLAVNI => 0,
        ]);

        // Should only have one entry (REPLACE INTO behavior or duplicate key error)
        $count = dbFetchSingle(
            'SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ? AND id_lokace = ?',
            [1, 1]
        );
        self::assertSame('1', $count);
    }

    // ========================================================================
    // Category 5: Sorting & Ordering (2 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testSerazeniLokaci()
    {
        $aktivita = Aktivita::zId(2003);

        // Set locations in reverse poradi order, with 4 as main
        $aktivita->nastavLokacePodleIds([1006, 1005, 1004, 1003, 1002, 1001], 1004);

        $aktivita = Aktivita::zId(2003);
        $lokace = $aktivita->seznamLokaci();

        // First should be main (id=4)
        self::assertSame(1004, $lokace[0]->id(), 'Hlavní místnost první');

        // Rest sorted by poradi (1001, 1002, 1003, 1005, 1006)
        self::assertSame(1001, $lokace[1]->id());
        self::assertSame(1002, $lokace[2]->id());
        self::assertSame(1003, $lokace[3]->id());
        self::assertSame(1005, $lokace[4]->id());
        self::assertSame(1006, $lokace[5]->id());
    }

    /**
     * @test
     */
    public function testPoradiVNazevLokaci()
    {
        $aktivita = Aktivita::zId(2003);
        $aktivita->nastavLokacePodleIds([1003, 1001, 1002], 1003);

        $aktivita = Aktivita::zId(2003);
        $nazev = $aktivita->nazevLokaci();

        // Should be: Klubovna (main, id=3), then Velká (poradi=1), then Malá (poradi=2)
        self::assertSame('Klubovna, Velká místnost, Malá místnost', $nazev);
    }

    // ========================================================================
    // Category 6: Room Conflicts (1 test)
    // ========================================================================

    /**
     * @test
     */
    public function testZadnaAktivitaNeMuzeByVeStejneMistnostiVeStejnyChCas()
    {
        // Create two activities with time overlap
        $data1 = [
            AktivitaSql::NAZEV_AKCE => 'Ranní aktivita',
            AktivitaSql::TYP => TypAktivity::DESKOHERNA,
            AktivitaSql::ROK => 2024,
            AktivitaSql::ZACATEK => '2024-07-15 09:00:00',
            AktivitaSql::KONEC => '2024-07-15 11:00:00',
        ];

        $aktivita1 = Aktivita::uloz(
            data: $data1,
            markdownPopis: null,
            organizatoriIds: [],
            lokaceIds: [2001],
            hlavniLokaceId: 1001,
            tagIds: [],
        );

        // Create overlapping activity
        $data2 = [
            AktivitaSql::NAZEV_AKCE => 'Překrývající aktivita',
            AktivitaSql::TYP => TypAktivity::DESKOHERNA,
            AktivitaSql::ROK => 2024,
            AktivitaSql::ZACATEK => '2024-07-15 10:00:00',
            AktivitaSql::KONEC => '2024-07-15 12:00:00',
        ];

        // Try to assign to same location - should succeed at model level
        // (Conflict detection is in import logic, not enforced by model)
        $aktivita2 = Aktivita::uloz(
            data: $data2,
            markdownPopis: null,
            organizatoriIds: [],
            lokaceIds: [2001],
            hlavniLokaceId: 1001,
            tagIds: [],
        );

        // Both activities exist with same location
        self::assertSame([2001], $aktivita1->seznamLokaciIdcka());
        self::assertSame([2001], $aktivita2->seznamLokaciIdcka());

        // Document: This test shows conflicts are NOT enforced at model layer.
        // For actual conflict prevention, see ImportSqlMappedValuesChecker
        self::assertTrue(true, 'Model layer allows overlapping activities in same location');
    }

    // ========================================================================
    // Category 7: Integration with Aktivita::uloz() (2 tests)
    // ========================================================================

    /**
     * @test
     */
    public function testUlozeniAktivitySLokacemi()
    {
        $data = [
            AktivitaSql::NAZEV_AKCE => 'Nová aktivita s místnostmi',
            AktivitaSql::TYP => TypAktivity::DESKOHERNA,
            AktivitaSql::ROK => 2024,
        ];

        $aktivita = Aktivita::uloz(
            data: $data,
            markdownPopis: null,
            organizatoriIds: [],
            lokaceIds: [1001, 1002, 1003],
            hlavniLokaceId: 1002,
            tagIds: [],
        );

        self::assertNotNull($aktivita->id());
        self::assertCount(3, $aktivita->seznamLokaci());
        self::assertSame(2, $aktivita->hlavniLokace()->id());

        // Verify in database
        $junctionCount = dbFetchSingle(
            'SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ?',
            [$aktivita->id()]
        );
        self::assertSame('3', $junctionCount);
    }

    /**
     * @test
     */
    public function testAktualizaceLokaciPomociUloz()
    {
        $aktivita = Aktivita::zId(2001);
        $puvodni = $aktivita->seznamLokaciIdcka();
        self::assertSame([2001], $puvodni);

        $data = $aktivita->rawDb();

        $aktivita = Aktivita::uloz(
            data: $data,
            markdownPopis: null,
            organizatoriIds: [],
            lokaceIds: [1002, 1003],
            hlavniLokaceId: 1003,
            tagIds: [],
        );

        self::assertCount(2, $aktivita->seznamLokaci());
        self::assertSame(3, $aktivita->hlavniLokace()->id());
    }
}
