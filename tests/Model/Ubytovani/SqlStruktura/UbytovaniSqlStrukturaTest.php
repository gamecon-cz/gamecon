<?php

namespace Gamecon\Tests\Model\Ubytovani\SqlStruktura;

use Gamecon\Tests\Model\AbstractTestSqlStruktura;
use Gamecon\Ubytovani\SqlStruktura\UbytovaniSqlStruktura;

class UbytovaniSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return UbytovaniSqlStruktura::class;
    }

}
