<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniLogSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkcePrihlaseniLogSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkcePrihlaseniLogSqlStruktura::class;
    }
}
