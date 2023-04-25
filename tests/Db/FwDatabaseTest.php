<?php

namespace Gamecon\Tests\Db;

use PHPUnit\Framework\TestCase;

class FwDatabaseTest extends TestCase
{
    public function testEmptyArrayParameterEscapedAsNull()
    {
        self::assertSame('NULL', dbQv([]));
    }
}
