<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Shop\Predmet;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractTestDb extends KernelTestCase
{
    /**
     * Gives every catalog row without one the variant production has for it, so fixtures can
     * insert legacy rows and still have purchases name what they bought.
     */
    protected const SQL_VYCHOZI_VARIANTY = <<<SQL
INSERT INTO product_variant (product_id, name, code, position, state, accommodation_day)
SELECT shop_predmety.id_predmetu, NULL, shop_predmety.kod_predmetu, 0, shop_predmety.stav, shop_predmety.ubytovani_den
FROM shop_predmety
WHERE NOT EXISTS (SELECT 1 FROM product_variant WHERE product_variant.code = shop_predmety.kod_predmetu)
SQL;

    /**
     * The variant of a catalog row, for a purchase inserted in SQL; `%s` is the row's id.
     */
    protected const SQL_VARIANTA_RADKU = '(SELECT product_variant.id FROM product_variant INNER JOIN shop_predmety AS radek ON radek.kod_predmetu = product_variant.code WHERE radek.id_predmetu = %s)';

    private static ?DbWrapper $connection = null;
    /**
     * @var string[]
     */
    protected static array $initQueries = [];
    protected static string $initData = '';
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
                $params = null;
                if (is_array($initQuery)) {
                    $initQuerySql = reset($initQuery);
                    $params = count($initQuery) > 1
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
                $dataset = new Dataset();
                $dataset->addCsv($initData);
                try {
                    self::$connection->import($dataset);
                } catch (\Throwable $throwable) {
                    self::$connection->import($dataset);
                    static::tearDownAfterClass();
                    throw $throwable;
                }
            }

            $initCallbacks = static::getBeforeClassInitCallbacks();
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
            static::zapomenNacteneEntity();
        }
        if (static::resetDbAfterSingleTestMethod()) {
            self::$connection->resetTestDb();
            static::zapomenNacteneEntity();
        }
        Aktivita::smazCache();
        \Uzivatel::smazCache();
        Predmet::smazCache();
    }

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return true;
    }

    protected static function resetDbAfterSingleTestMethod(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return static::$initQueries;
    }

    protected static function getInitData(): string
    {
        return (string) static::$initData;
    }

    /**
     * @return array<callable>
     */
    protected static function getBeforeClassInitCallbacks(): array
    {
        return [];
    }

    public static function tearDownAfterClass(): void
    {
        if (static::keepTestClassDbChangesInTransaction()) {
            self::$connection->rollback();
        }
        if (static::resetDbAfterClass()) {
            self::$connection->resetTestDb();
        }
        static::zapomenNacteneEntity();
        if (static::$disableStrictTransTables) {
            static::disableStrictTransTables();
        }
        Aktivita::smazCache();
        \Uzivatel::smazCache();
        Predmet::smazCache();
    }

    /**
     * Entities loaded before a rollback or reset describe rows that are gone, and a reset hands
     * their ids to new rows. Legacy code writes through the global kernel, not the test one.
     */
    protected static function zapomenNacteneEntity(): void
    {
        $kernely = [SystemoveNastaveni::zGlobals()->kernel()];
        if (static::$booted) {
            $kernely[] = static::$kernel;
        }
        foreach ($kernely as $kernel) {
            $doctrine = $kernel->getContainer()->get('doctrine');
            // A failed flush closes the manager, and clear() does not reopen it.
            $doctrine->getManager()->isOpen()
                ? $doctrine->getManager()->clear()
                : $doctrine->resetManager();
        }
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
SHOW COLUMNS FROM {$tabulka}
SQL,
        );

        return array_map(
            fn (
                array $row,
            ) => reset($row),
            $result->fetchAll(\PDO::FETCH_NUM),
        );
    }
}
