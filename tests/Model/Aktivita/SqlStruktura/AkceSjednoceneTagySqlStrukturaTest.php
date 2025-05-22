<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceSjednoceneTagySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceSjednoceneTagySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceSjednoceneTagySqlStruktura::class;
    }
}
