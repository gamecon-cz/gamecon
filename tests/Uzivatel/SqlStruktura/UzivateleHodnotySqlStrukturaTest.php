<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel\SqlStruktura;

use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura;

class UzivateleHodnotySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return UzivateleHodnotySqlStruktura::class;
    }
}
