<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceLokaceSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceLokaceSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceLokaceSqlStruktura::class;
    }
}
