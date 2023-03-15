<?php

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class PredmetSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return PredmetSqlStruktura::class;
    }

}
