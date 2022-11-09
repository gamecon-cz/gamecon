<?php

namespace Gamecon\Tests\Db;

class DbTest extends \PHPUnit\Framework\TestCase
{
    /** @var DbWrapper */
    private static $connection;
    /** @var string[] */
    protected static array $initQueries = [];
    protected static string $initData = '';

    static function setConnection(DbWrapper $connection) {
        self::$connection = $connection;
    }

    public function setUp(): void {
        self::$connection->begin();
    }

    static function setUpBeforeClass(): void {
        self::$connection->begin();

        foreach (static::getInitQueries() as $initQuery) {
            self::$connection->query($initQuery);
        }

        $initData = static::getInitData();
        if ($initData) {
            $dataset = new Dataset;
            $dataset->addCsv($initData);
            self::$connection->import($dataset);
        }
    }

    protected static function getInitQueries(): array {
        return static::$initQueries;
    }

    protected static function getInitData(): string {
        return (string)static::$initData;
    }

    protected function tearDown(): void {
        self::$connection->rollback();
    }

    public static function tearDownAfterClass(): void {
        self::$connection->rollback();
    }

}
