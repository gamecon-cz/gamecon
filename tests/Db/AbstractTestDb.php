<?php

namespace Gamecon\Tests\Db;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

abstract class AbstractTestDb extends KernelTestCase
{
    use Factories;
    
    private static ?DbWrapper $connection = null;
    /** @var string[] */
    protected static array  $initQueries = [];
    protected static string $initData    = '';
    // například pro vypnutí kontroly "Field 'cena' doesn't have a default value"
    protected static bool $disableStrictTransTables = false;

    protected bool $revertDbChangesAfterTest = true;

    public static function setConnection(DbWrapper $connection): void
    {
        self::$connection = $connection;
    }

    public static function setUpBeforeClass(): void
    {
        // Boot the kernel early to ensure it uses the correct DB_NAME constant
        static::bootKernel();

        try {
            if (static::keepTestClassDbChangesInTransaction()) {
                self::$connection->begin();
            }

            if (static::$disableStrictTransTables) {
                static::disableStrictTransTables();
            }

            foreach (static::getSetUpBeforeClassInitQueries() as $initQuery) {
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
        } catch (\Throwable $throwable) {
            echo ($throwable->getMessage() . '; ' . $throwable->getTraceAsString()) . PHP_EOL;
            throw $throwable;
        }
    }

    protected function setUp(): void
    {
        if (static::keepSingleTestMethodDbChangesInTransaction()) {
            // note that any structure changes trigger implicit COMMIT
            self::$connection->begin();
        }
    }

    protected function tearDown(): void
    {
        if (static::keepSingleTestMethodDbChangesInTransaction()) {
            self::$connection->rollback();
        }
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $systemoveNastaveni->queryCache()->clear();
        $systemoveNastaveni->db()->clearPrefetchedDataVersions();
    }

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return true;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return static::$initQueries;
    }

    protected static function getInitData(): string
    {
        return (string)static::$initData;
    }

    /**
     * @return array<callable>
     */
    protected static function getInitCallbacks(): array
    {
        return [];
    }

    public static function tearDownAfterClass(): void
    {
        if (static::keepTestClassDbChangesInTransaction()) {
            self::$connection->rollback();
        }
        if (static::$disableStrictTransTables) {
            static::disableStrictTransTables();
        }
        SystemoveNastaveni::zGlobals()->queryCache()->clear();
        SystemoveNastaveni::zGlobals()->db()->clearPrefetchedDataVersions();
    }

    // například pro vypnutí kontroly "Field 'cena' doesn't have a default value"
    protected static function disableStrictTransTables()
    {
        self::$connection->query(<<<SQL
SET SESSION sql_mode = REGEXP_REPLACE(@@SESSION.sql_mode, 'STRICT_TRANS_TABLES,?', '')
SQL,
        );
    }

    protected static function enableStrictTransTables()
    {
        self::$connection->query(<<<SQL
SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'STRICT_TRANS_TABLES')
SQL,
        );
    }

    protected function nazvySloupcuTabulky(string $tabulka): array
    {
        $result = self::$connection->query(<<<SQL
SHOW COLUMNS FROM $tabulka
SQL,
        );

        return array_map(
            fn(
                array $row,
            ) => reset($row),
            mysqli_fetch_all($result),
        );
    }

}
