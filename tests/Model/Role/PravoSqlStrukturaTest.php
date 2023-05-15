<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\SqlStruktura\PravoSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class PravoSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return PravoSqlStruktura::class;
    }

}
