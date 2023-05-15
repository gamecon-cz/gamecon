<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\SqlStruktura\RoleSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class RoleSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return RoleSqlStruktura::class;
    }
}
