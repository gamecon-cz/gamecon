<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AktivitaSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return AkceSeznamSqlStruktura::class;
    }

}
