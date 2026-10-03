<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use PHPUnit\Framework\TestCase;

class FwDatabaseTest extends TestCase
{
    public function testLegacyConnectionHoldsAMegabyteOfGroupConcat(): void
    {
        self::assertSame('1048576', dbOneCol('SELECT @@group_concat_max_len'));
    }

    public function testLegacyConnectionComparesStringsInCzech(): void
    {
        self::assertSame('utf8mb4_czech_ci', dbOneCol('SELECT @@collation_connection'));
    }

    public function testEmptyArrayParameterEscapedAsNull()
    {
        self::assertSame('NULL', dbQv([]));
    }
}
