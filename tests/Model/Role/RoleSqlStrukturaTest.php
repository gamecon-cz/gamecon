<?php

namespace Gamecon\Tests\Model\Role;

use Gamecon\Role\SqlStruktura\RoleSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class RoleSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return RoleSqlStruktura::class;
    }
}
