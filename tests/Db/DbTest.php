<?php

namespace Gamecon\Tests\Db;

class DbTest extends \PHPUnit\Framework\TestCase
{
    private static $connection;
    protected static $initData;

    static function setConnection(DbWrapper $connection)
    {
        self::$connection = $connection;
    }

    public function setUp(): void
    {
        self::$connection->begin();
    }

    static function setUpBeforeClass(): void
    {
        self::$connection->begin();

        if (isset(static::$initData)) {
            $dataset = new Dataset;
            $dataset->addCsv(static::$initData);
            self::$connection->import($dataset);
        }
    }

    public function tearDown(): void
    {
        self::$connection->rollback();
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection->rollback();
    }

}
