<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin\SqlStruktura;

use Gamecon\Admin\SqlStruktura\UzivateleVzhledAdminuPouzitiSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class UzivateleVzhledAdminuPouzitiSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return UzivateleVzhledAdminuPouzitiSqlStruktura::class;
    }
}
