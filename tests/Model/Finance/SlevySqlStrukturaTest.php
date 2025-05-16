<?php

namespace Gamecon\Tests\Model\Finance;

use Gamecon\Finance\SqlStruktura\SlevySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class SlevySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return SlevySqlStruktura::class;
    }

}
