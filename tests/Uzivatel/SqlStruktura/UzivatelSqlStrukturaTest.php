<?php

namespace Gamecon\Tests\Uzivatel\SqlStruktura;

use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura;

class UzivatelSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return UzivatelSqlStruktura::class;
    }

}
