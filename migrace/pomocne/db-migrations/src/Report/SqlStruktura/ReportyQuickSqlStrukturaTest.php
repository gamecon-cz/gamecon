<?php

namespace Godric\DbMigrations\Report\SqlStruktura;

use Gamecon\Report\SqlStruktura\ReportyQuickSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use PHPUnit\Framework\TestCase;

class ReportyQuickSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return ReportyQuickSqlStruktura::class;
    }

}
