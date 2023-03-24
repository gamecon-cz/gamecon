<?php

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class PredmetSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return PredmetSqlStruktura::class;
    }

}
