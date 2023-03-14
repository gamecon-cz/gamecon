<?php

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\PredmetSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;
use PHPUnit\Framework\TestCase;

class PredmetSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return PredmetSqlStruktura::class;
    }

}
