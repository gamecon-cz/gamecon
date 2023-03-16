<?php

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class SystemoveNastaveniSqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return SystemoveNastaveniSqlStruktura::class;
    }

}
