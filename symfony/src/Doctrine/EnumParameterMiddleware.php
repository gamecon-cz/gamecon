<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Does for raw DBAL queries what the legacy `dbQuery()` already does with a backed enum parameter.
 */
final class EnumParameterMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new EnumParameterDriver($driver);
    }
}
