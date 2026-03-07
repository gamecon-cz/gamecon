<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSpecSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkcePrihlaseniSpecSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkcePrihlaseniSpecSqlStruktura::class;
    }
}
