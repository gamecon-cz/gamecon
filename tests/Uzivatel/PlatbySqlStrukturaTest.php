<?php

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use Gamecon\Uzivatel\PlatbySqlStruktura;

class PlatbySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return PlatbySqlStruktura::class;
    }

}
