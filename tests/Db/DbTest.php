<?php

namespace Gamecon\Tests\Db;

class DbTest extends \PHPUnit\Framework\TestCase
{
    /** @var DbWrapper */
    private static $connection;
    /** @var string[] */
    protected static array $initQueries = [];
    protected static string $initData = '';

    protected $revertDbChangesAfterTest = true;

    static function setConnection(DbWrapper $connection) {
        self::$connection = $connection;
    }

    public function setUp(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->begin();
        }
    }

    static function setUpBeforeClass(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->begin();
        }

        foreach (static::getInitQueries() as $index => $initQuery) {
            $initQuerySql = $initQuery;
            $params       = null;
            if (is_array($initQuery)) {
                $initQuerySql = reset($initQuery);
                $params       = count($initQuery) > 1
                    ? end($initQuery)
                    : null;
            }
            self::$connection->query($initQuerySql, $params);
        }

        $initData = static::getInitData();
        if ($initData) {
            $dataset = new Dataset;
            $dataset->addCsv($initData);
            self::$connection->import($dataset);
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

    protected function tearDown(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->rollback();
        }
    }

    public static function tearDownAfterClass(): void {
        if (static::keepDbChangesInTransaction()) {
            self::$connection->rollback();
        }
    }

}
