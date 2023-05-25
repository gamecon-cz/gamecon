<?php

namespace Gamecon\Tests\Db;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestDb extends TestCase
{
    /** @var DbWrapper */
    private static $connection;
    /** @var string[] */
    protected static array $initQueries = [];
    protected static string $initData = '';
    // například pro vypnutí kontroly "Field 'cena' doesn't have a default value"
    protected static bool $disableStrictTransTables = false;

    protected $revertDbChangesAfterTest = true;

    static function setConnection(DbWrapper $connection) {
        self::$connection = $connection;
    }

    protected function setUp(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->begin();
        }
    }

    protected function tearDown(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->rollback();
        }
    }

    static function setUpBeforeClass(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->begin();
        }

        if (static::$disableStrictTransTables) {
            static::disableStrictTransTables();
        }

        foreach (static::getInitQueries() as $initQuery) {
            $initQuerySql = $initQuery;
            $params       = null;
            if (is_array($initQuery)) {
                $initQuerySql = reset($initQuery);
                $params       = count($initQuery) > 1
                    ? end($initQuery)
                    : null;
            }
            try {
                self::$connection->query($initQuerySql, $params);
            } catch (\Throwable $throwable) {
                static::tearDownAfterClass();
                throw $throwable;
            }
        }

        $initData = static::getInitData();
        if ($initData) {
            $dataset = new Dataset;
            $dataset->addCsv($initData);
            try {
                self::$connection->import($dataset);
            } catch (\Throwable $throwable) {
                self::$connection->import($dataset);
                static::tearDownAfterClass();
                throw $throwable;
            }
        }

        $initCallbacks = static::getInitCallbacks();
        foreach ($initCallbacks as $initCallback) {
            $initCallback();
        }
    }

    protected static function keepDbChangesInTransaction(): bool {
        return true;
    }

    protected static function getInitQueries(): array {
        return static::$initQueries;
    }

    protected static function getInitData(): string {
        return (string)static::$initData;
    }

    /**
     * @return array<callable>
     */
    protected static function getInitCallbacks(): array {
        return [];
    }

    public static function tearDownAfterClass(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->rollback();
        }
        if (static::$disableStrictTransTables) {
            static::disableStrictTransTables();
        }
    }

    // například pro vypnutí kontroly "Field 'cena' doesn't have a default value"
    protected static function disableStrictTransTables() {
        self::$connection->query(<<<SQL
SET SESSION sql_mode = REGEXP_REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES,?', '')
SQL
        );
    }

    protected static function enableStrictTransTables() {
        self::$connection->query(<<<SQL
SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'STRICT_TRANS_TABLES')
SQL
        );
    }

    protected function nazvySloupcuTabulky(string $tabulka): array {
        $result = self::$connection->query(<<<SQL
SHOW COLUMNS FROM $tabulka
SQL
        );
        return array_map(
            fn(array $row) => reset($row),
            mysqli_fetch_all($result)
        );
    }

}
