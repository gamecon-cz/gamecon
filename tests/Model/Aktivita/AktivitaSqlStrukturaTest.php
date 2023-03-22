<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AktivitaSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AktivitaSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return AktivitaSqlStruktura::class;
    }

}
