<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\AktivitaSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class AktivitaSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return AktivitaSqlStruktura::class;
    }

}
