<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop\SqlStruktura;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class PredmetSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return PredmetSqlStruktura::class;
    }

}
