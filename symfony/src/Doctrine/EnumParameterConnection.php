<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;

final class EnumParameterConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        return new EnumParameterStatement(parent::prepare($sql));
    }
}
