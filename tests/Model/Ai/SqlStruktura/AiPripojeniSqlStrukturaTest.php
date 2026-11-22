<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ai\SqlStruktura;

use Gamecon\Ai\SqlStruktura\AiPripojeniSqlStruktura;
use Gamecon\Tests\Model\AbstractTestSqlStruktura;

class AiPripojeniSqlStrukturaTest extends AbstractTestSqlStruktura
{
    protected function strukturaClass(): string
    {
        return AiPripojeniSqlStruktura::class;
    }
}
