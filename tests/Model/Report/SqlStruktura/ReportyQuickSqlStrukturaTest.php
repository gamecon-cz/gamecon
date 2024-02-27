<?php

namespace Gamecon\Tests\Model\Report\SqlStruktura;

use Gamecon\Report\SqlStruktura\ReportyQuickSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class ReportyQuickSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return ReportyQuickSqlStruktura::class;
    }

}
