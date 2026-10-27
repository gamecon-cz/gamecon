<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\Korektura\SqlStruktura;

use Gamecon\Aktivita\Korektura\SqlStruktura\AkceKorekturaSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceKorekturaSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceKorekturaSqlStruktura::class;
    }
}
