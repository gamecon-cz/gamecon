<?php

namespace Gamecon\Tests\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniStavySql;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use PHPUnit\Framework\TestCase;

class AkcePrihlaseniStavySqlTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkcePrihlaseniStavySql::class;
    }

}
