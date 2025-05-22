<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\KategorieSjednocenychTaguSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class KategorieSjednocenychTaguSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return KategorieSjednocenychTaguSqlStruktura::class;
    }
}
