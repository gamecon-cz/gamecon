<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\SqlStruktura;

use Gamecon\Admin\SqlStruktura\UzivateleVzhledAdminuSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class UzivateleVzhledAdminuSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return UzivateleVzhledAdminuSqlStruktura::class;
    }
}
