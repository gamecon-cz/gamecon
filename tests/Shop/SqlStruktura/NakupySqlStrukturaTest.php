<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop\SqlStruktura;

use Gamecon\Shop\SqlStruktura\NakupySqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class NakupySqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return NakupySqlStruktura::class;
    }

}
