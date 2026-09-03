<?php

declare(strict_types=1);

namespace App\Tests\Db;

use PHPUnit\Framework\TestCase;

/**
 * Guards the promise of AbstractDatabaseKernelTestCase: a Symfony-stack test
 * that writes to the database must leave nothing behind.
 *
 * Reads through the legacy connection on purpose — it sits outside any Doctrine
 * transaction, so it sees only rows that were actually committed. A probe using
 * the Doctrine connection would be blind to the failure it is meant to catch,
 * because inside the run it cannot see another test class's rolled-back rows
 * either way.
 */
class DatabaseCleanupTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function writtenByTests(): array
    {
        return [
            'users created by API tests' => [
                'uzivatele_hodnoty',
                "SELECT COUNT(*) FROM uzivatele_hodnoty WHERE login_uzivatele LIKE 'api_test_%'",
            ],
            'products created by API tests' => [
                'shop_predmety',
                "SELECT COUNT(*) FROM shop_predmety WHERE kod_predmetu LIKE 'API-TEST-%'",
            ],
            'tags created by API tests' => [
                'product_tag',
                "SELECT COUNT(*) FROM product_tag WHERE code LIKE 'api-test-%'",
            ],
            'activities created by the listener test' => [
                'akce_seznam',
                "SELECT COUNT(*) FROM akce_seznam WHERE nazev_akce = 'Test Activity'",
            ],
        ];
    }

    /**
     * @dataProvider writtenByTests
     */
    public function testNothingWasCommitted(string $table, string $countQuery): void
    {
        $this->assertSame(
            0,
            (int) \dbFetchSingle($countQuery),
            sprintf(
                'Rows left in %s. A test wrote them outside its transaction — the usual cause is calling '
                . 'bootKernel() inside a test extending AbstractDatabaseKernelTestCase, which shuts down the '
                . 'kernel whose connection holds that transaction.',
                $table,
            ),
        );
    }
}
