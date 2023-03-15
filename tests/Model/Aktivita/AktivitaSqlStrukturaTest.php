<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AktivitaSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class AktivitaSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return AktivitaSqlStruktura::class;
    }

}
