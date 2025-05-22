<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceTypySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceTypySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceTypySqlStruktura::class;
    }
}
