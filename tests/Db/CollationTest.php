<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Shop\Predmet;

class CollationTest extends AbstractTestDb
{
    public function testDatabazeTabulkyISloupceMajiJednotneUtf8mb4CzechCi()
    {
        self::assertSame(
            'utf8mb4_czech_ci',
            dbOneCol('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'),
        );

        self::assertSame(
            [],
            dbFetchColumn(<<<SQL
                SELECT CONCAT(TABLE_NAME, ' ', TABLE_COLLATION)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_TYPE = 'BASE TABLE'
                    AND TABLE_COLLATION <> 'utf8mb4_czech_ci'
                SQL,
            ),
        );

        // *_bin sloupce jsou binární záměrně (Doctrine JSON)
        self::assertSame(
            [],
            dbFetchColumn(<<<SQL
                SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME, ' ', COLLATION_NAME)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND COLLATION_NAME <> 'utf8mb4_czech_ci'
                    AND COLLATION_NAME <> 'utf8mb4_bin'
                SQL,
            ),
        );
    }

    public function testSpojeniUlozi4bajtoveZnaky()
    {
        dbQuery("INSERT INTO _vars (name, value) VALUES ('test-emoji', $0)", ['🎲 kostka']);

        self::assertSame('🎲 kostka', dbOneCol("SELECT value FROM _vars WHERE name = 'test-emoji'"));
    }

    public function testHledaniPredmetuPodleNazvuAKoduNepadaNaCollation()
    {
        self::assertNull(Predmet::letosniPlacka(1990));
    }
}
