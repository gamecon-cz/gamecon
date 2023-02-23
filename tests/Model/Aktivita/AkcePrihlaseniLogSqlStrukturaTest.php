<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\AkcePrihlaseniLogSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class AkcePrihlaseniLogSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return AkcePrihlaseniLogSqlStruktura::class;
    }

}
