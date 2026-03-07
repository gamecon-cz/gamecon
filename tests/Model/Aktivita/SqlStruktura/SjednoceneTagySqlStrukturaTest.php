<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\SjednoceneTagySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class SjednoceneTagySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return SjednoceneTagySqlStruktura::class;
    }
}
