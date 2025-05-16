<?php

namespace Gamecon\Tests\Model\Finance;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSpecSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkcePrihlaseniSpecStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkcePrihlaseniSqlStruktura::class;
    }

}
