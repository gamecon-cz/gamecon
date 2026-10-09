<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\ParameterType;

final class EnumParameterStatement extends AbstractStatementMiddleware
{
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
            // DBAL passes STRING both as its default and when asked for explicitly, so an int enum cannot be told apart.
            if (is_int($value) && $type === ParameterType::STRING) {
                $type = ParameterType::INTEGER;
            }
        } elseif ($value instanceof \UnitEnum) {
            $zprava = sprintf('Enum %s has no value, so it cannot be a query parameter; only a backed enum can.', $value::class);

            throw new \InvalidArgumentException($zprava);
        }

        return parent::bindValue($param, $value, $type);
    }
}
