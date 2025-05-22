<?php

namespace Gamecon\Tests\Model\Aktivita\SqlStruktura;

use Gamecon\Aktivita\SqlStruktura\AkceOrganizatoriSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AkceOrganizatoriSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AkceOrganizatoriSqlStruktura::class;
    }
}
