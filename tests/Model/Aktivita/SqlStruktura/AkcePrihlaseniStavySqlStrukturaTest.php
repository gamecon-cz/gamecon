<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniStavySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkcePrihlaseniStavySqlStrukturaTest extends AbstractTestSqlStruktura
{

    protected function strukturaClass(): string
    {
        return AkcePrihlaseniStavySqlStruktura::class;
    }
}
