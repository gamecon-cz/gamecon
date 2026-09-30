<?php

declare(strict_types=1);

namespace App\Tests\Db;

use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Legacy code runs on this connection too, so it carries the session legacy was written
 * against: Czech collation for string comparisons, values fetched as strings.
 */
class NastaveniSpojeniTest extends AbstractDatabaseKernelTestCase
{
    public function testComparesStringsInCzech(): void
    {
        self::assertSame('utf8mb4_czech_ci', $this->connection()->fetchOne('SELECT @@collation_connection'));
    }

    public function testGroupConcatHoldsAMegabyte(): void
    {
        self::assertSame('1048576', $this->connection()->fetchOne('SELECT @@group_concat_max_len'));
    }

    /**
     * Callers read the count as "changed", e.g. to skip a cache refresh when a setting is saved unchanged.
     */
    public function testUpdateCountsOnlyChangedRows(): void
    {
        self::assertSame(0, $this->connection()->executeStatement(
            "UPDATE systemove_nastaveni SET hodnota = hodnota WHERE klic = 'ROCNIK'",
        ));
    }

    public function testFetchesValuesAsStrings(): void
    {
        self::assertSame('1', $this->connection()->fetchOne('SELECT 1'));
    }
}
