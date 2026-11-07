<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

/**
 * `dbFetch()` reads and `dbExecute()` writes; the caller says which, so nothing is guessed
 * from the text of the statement. A call must not leave results pending on the connection
 * (`PDO::exec()` of a statement that returns rows does, and the next query fails): the tests
 * end with a query that would fail then.
 */
class DbFetchExecuteTest extends AbstractTestDb
{
    protected function setUp(): void
    {
        parent::setUp();
        dbExecute('DROP TEMPORARY TABLE IF EXISTS tmp_fetch_execute');
        dbExecute('CREATE TEMPORARY TABLE tmp_fetch_execute (cislo INT NOT NULL PRIMARY KEY)');
    }

    /**
     * @dataProvider readingStatements
     */
    public function testFetchReturnsRowsWhateverTheStatementStartsWith(string $query): void
    {
        $result = dbFetch($query);

        self::assertInstanceOf(\PDOStatement::class, $result);
        // Spojení má ATTR_STRINGIFY_FETCHES, takže i čísla přicházejí jako řetězce.
        self::assertSame([['1']], $result->fetchAll(\PDO::FETCH_NUM));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readingStatements(): iterable
    {
        yield 'SELECT' => ['SELECT 1'];
        yield 'WITH' => ['WITH x AS (SELECT 1 AS a) SELECT a FROM x'];
        yield 'parenthesis' => ['(SELECT 1) UNION (SELECT 1)'];
        yield 'comment before the statement' => ["-- poznámka\nSELECT 1"];
        yield 'VALUES' => ['VALUES (1)'];
    }

    public function testFetchBindsBothKindsOfPlaceholders(): void
    {
        self::assertSame([['5']], dbFetch('SELECT $0', [5])->fetchAll(\PDO::FETCH_NUM));
        self::assertSame([['x']], dbFetch('SELECT ?', ['x'])->fetchAll(\PDO::FETCH_NUM));
        self::assertSame([['7']], dbFetch('SELECT $1', [7])->fetchAll(\PDO::FETCH_NUM));
    }

    public function testExecuteReturnsTheNumberOfAffectedRows(): void
    {
        self::assertSame(3, dbExecute('INSERT INTO tmp_fetch_execute VALUES (1), (2), (3)'));
        self::assertSame(1, dbExecute('UPDATE tmp_fetch_execute SET cislo = 10 WHERE cislo = 1'));
        self::assertSame(2, dbExecute('DELETE FROM tmp_fetch_execute WHERE cislo > 2'));
        self::assertSame('1', dbFetchSingle('SELECT COUNT(*) FROM tmp_fetch_execute'));
    }

    public function testExecuteOfAStatementWithoutRowsReturnsZero(): void
    {
        self::assertSame(0, dbExecute('SET @tmp_fetch_execute = 1'));
        self::assertSame(0, dbExecute('DROP TEMPORARY TABLE IF EXISTS tmp_fetch_execute_other'));
        self::assertSame('1', dbFetchSingle('SELECT @tmp_fetch_execute'));
    }

    public function testExecuteBindsPlaceholders(): void
    {
        self::assertSame(2, dbExecute('INSERT INTO tmp_fetch_execute VALUES ($0), ($1)', [3, 4]));
        self::assertSame(1, dbExecute('DELETE FROM tmp_fetch_execute WHERE cislo = ?', [3]));
        self::assertSame('1', dbFetchSingle('SELECT COUNT(*) FROM tmp_fetch_execute'));
    }

    /**
     * The shared connection does not allow several statements in one query; the ones made
     * by `_dbConnect()` do, and migrations run through them.
     */
    public function testExecuteWalksThroughEveryResultOfAMultiStatementQuery(): void
    {
        $pdo = dbConnectTemporary();
        dbExecute('CREATE TEMPORARY TABLE tmp_fetch_execute_multi (cislo INT NOT NULL)', [], $pdo);

        $affected = dbExecute(
            'INSERT INTO tmp_fetch_execute_multi VALUES (7); SELECT 1; INSERT INTO tmp_fetch_execute_multi VALUES (8)',
            [],
            $pdo,
        );

        self::assertSame(1, $affected, 'the count of the first statement');
        self::assertSame('2', dbFetch('SELECT COUNT(*) FROM tmp_fetch_execute_multi', [], $pdo)->fetchColumn());
    }

    public function testExecuteReportsAnErrorOfALaterStatement(): void
    {
        $pdo = dbConnectTemporary();
        dbExecute('CREATE TEMPORARY TABLE tmp_fetch_execute_later (cislo INT NOT NULL PRIMARY KEY)', [], $pdo);

        $this->expectException(\DbDuplicateEntryException::class);

        dbExecute('INSERT INTO tmp_fetch_execute_later VALUES (1); INSERT INTO tmp_fetch_execute_later VALUES (1)', [], $pdo);
    }

    public function testExecuteOfAReadLeavesNoCursorOpen(): void
    {
        dbExecute('SELECT 1');

        self::assertSame('1', dbFetchSingle('SELECT 1'));
    }

    public function testExecuteReportsADuplicateEntryAsItsOwnException(): void
    {
        dbExecute('INSERT INTO tmp_fetch_execute VALUES (1)');

        $this->expectException(\DbDuplicateEntryException::class);

        dbExecute('INSERT INTO tmp_fetch_execute VALUES (1)');
    }

    public function testFetchReportsABrokenStatementAsDbException(): void
    {
        $this->expectException(\DbException::class);

        dbFetch('SELEKT 1');
    }

    public function testBothAreCountedAndRemembered(): void
    {
        $before = dbNumQ();

        dbFetch('SELECT 1')->fetchAll();
        dbExecute('SET @tmp_fetch_execute = 2');

        self::assertSame($before + 2, dbNumQ());
        self::assertSame('SET @tmp_fetch_execute = 2', dbLastQ());
    }

    public function testHelpersReadWhateverTheStatementStartsWith(): void
    {
        $query = 'WITH dve AS (SELECT 1 AS cislo UNION SELECT 2) SELECT cislo FROM dve ORDER BY cislo';

        self::assertSame([[
            'cislo' => '1',
        ], [
            'cislo' => '2',
        ]], dbFetchAll($query));
        self::assertSame('1', dbFetchSingle($query));
        self::assertSame(['1', '2'], dbFetchColumn($query));
        self::assertSame(['1', '2'], dbOneArray($query));
    }
}
