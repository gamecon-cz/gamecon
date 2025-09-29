<?php declare(strict_types=1);

namespace Granam\Scalar\Tools;

use Granam\Strict\Object\StrictObject;
use Granam\Tools\ValueDescriber;

class ToScalar extends StrictObject
{

    /**
     * @param bool|float|int|string|null|object $value
     * @param bool $strict = true Null raises and exception by default
     *
     * @return int|float|null|string
     * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
     */
    public static function toScalar($value, bool $strict = true)
    {
        if (\is_scalar($value)) {
            return $value;
        }

        if ($value === null) {
            if (!$strict) {
                return null;
            }
            throw new Exceptions\WrongParameterType(
                'Expected scalar or object with __toString method on strict mode, got ' . ValueDescriber::describe($value)
            );
        }

        return ToString::toString($value);
    }
}
