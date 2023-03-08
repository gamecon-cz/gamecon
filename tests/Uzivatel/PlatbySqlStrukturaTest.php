<?php

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Model\SqlStrukturaTest;
use Gamecon\Uzivatel\PlatbySqlStruktura;

class PlatbySqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return PlatbySqlStruktura::class;
    }

}
