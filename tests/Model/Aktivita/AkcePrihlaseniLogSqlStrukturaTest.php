<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniLogSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkcePrihlaseniLogSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return AkcePrihlaseniLogSqlStruktura::class;
    }

}
