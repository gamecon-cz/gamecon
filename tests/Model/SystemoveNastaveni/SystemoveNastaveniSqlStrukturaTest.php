<?php

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class SystemoveNastaveniSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string {
        return SystemoveNastaveniSqlStruktura::class;
    }

}
