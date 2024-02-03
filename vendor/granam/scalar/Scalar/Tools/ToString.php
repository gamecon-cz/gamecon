<?php declare(strict_types = 1);

namespace Granam\Scalar\Tools;

use Granam\Scalar\ScalarInterface;
use Granam\Strict\Object\StrictObject;
use Granam\Tools\ValueDescriber;

class ToString extends StrictObject
{
	/**
	 * @param null|int|float|string|ScalarInterface $value
	 * @param bool $strict = true if NULL should raise an exception otherwise is NULL turned into empty string
	 * @return string
	 * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
	 */
	public static function toString($value, bool $strict = true): string
	{
		if (\is_string($value)) {
			return $value;
		}

		if (\is_scalar($value)
			|| ($value === null && !$strict)
			|| (\is_object($value) && \method_exists($value, '__toString'))
		) {
			return (string) $value;
		}
		if ($strict) {
			if ($value !== null) {
				$message = 'Expected scalar or object with __toString method, got ' . ValueDescriber::describe($value);
			} else {
				$message = 'In strict mode expected scalar or object with __toString method, got ' . ValueDescriber::describe($value);
			}
		} else {
			$message = 'Expected scalar, NULL or object with __toString method, got ' . ValueDescriber::describe($value);
		}

		throw new Exceptions\WrongParameterType($message);
	}

	/**
	 * @param null|int|float|string|ScalarInterface $value
	 * @return string
	 * @throws \Granam\Scalar\Tools\Exceptions\WrongParameterType
	 */
	public static function toStringOrNull($value): ?string
	{
		if ($value === null) {
			return null;
		}

		return static::toString($value);
	}
}
