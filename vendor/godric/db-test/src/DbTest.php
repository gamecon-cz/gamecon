<?php

namespace Godric\DbTest;

class DbTest extends \PHPUnit\Framework\TestCase {

    private static $connection;

    static function setConnection(DbWrapper $connection) {
        self::$connection = $connection;
    }

    function setUp() {
        self::$connection->begin();
    }

    static function setUpBeforeClass() {
        self::$connection->begin();

        if(isset(static::$initData)) {
            $dataset = new Dataset;
            $dataset->addCsv(static::$initData);
            self::$connection->import($dataset);
        }
    }

    function tearDown() {
        self::$connection->rollback();
    }

    static function tearDownAfterClass() {
        self::$connection->rollback();
    }

}
