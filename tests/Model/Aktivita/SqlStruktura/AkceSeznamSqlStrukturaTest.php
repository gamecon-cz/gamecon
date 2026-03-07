<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceSeznamSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceSeznamSqlStruktura::class;
    }
}
