<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\SqlStruktura\PravoSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class PravoSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return PravoSqlStruktura::class;
    }

}
