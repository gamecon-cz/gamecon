<?php declare(strict_types=1);

namespace Granam\Tools;

use Granam\Strict\Object\StrictObject;

class ValueDescriber extends StrictObject
{
    /**
     * @param mixed $value ...
     * @return string
     */
    public static function describe($value): string
    {
        if (func_num_args() === 1) {
            return self::describeSingleValue($value);
        }

        return implode(
            ',',
            array_map(
                static function ($value) {
                    return self::describeSingleValue($value);
                },
                func_get_args()
            )
        );
    }

    private static function describeSingleValue($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string)var_export($value, true);
        }
        if (is_array($value)) {
            ob_start();
            var_dump($value);
            $dumped = ob_get_clean();
            $withoutItemsCount = preg_replace('~array\(\d+\)~', 'array', $dumped);
            $withoutEndingEmptyLine = preg_replace('~\n+\}\s*~', '}', $withoutItemsCount);
            $withoutNewLineAfterArrayIndex = preg_replace('~\[(\d+)\](=>)\n\s*~', '$1 $2 ', $withoutEndingEmptyLine);

            return $withoutNewLineAfterArrayIndex;
        }

        if (is_object($value)) {
            $description = 'instance of \\' . get_class($value);
            if (method_exists($value, '__toString') && is_callable([$value, '__toString'])) {
                $description .= ' (' . $value . ')';
            } elseif ($value instanceof \DateTime) {
                $description .= ' (' . $value->format(DATE_ATOM) . ')';
            }

            return $description;
        }

        return gettype($value);
    }
}