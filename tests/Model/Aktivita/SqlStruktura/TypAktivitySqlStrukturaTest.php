<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\TypAktivitySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class TypAktivitySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return TypAktivitySqlStruktura::class;
    }

}
