<?php

namespace Rikudou\Iban\Helper;

use DivisionByZeroError;
use InvalidArgumentException;
use LogicException;

class Utils
{
    public const BCMOD_USE_CUSTOM = 2 << 0;
    public const BCMOD_USE_BCMATH = 2 << 1;
    public const BCMOD_USE_GMP = 2 << 2;

    /**
     * @param string   $dividend
     * @param string   $divisor
     * @param bool|int $ownImplementation
     *
     * @return string
     */
    public static function bcmod(string $dividend, string $divisor, $ownImplementation = false): string
    {
        $dividend = ltrim($dividend, '0');
        $divisor = ltrim($divisor, '0');
        if ($divisor === '') {
            throw new DivisionByZeroError('Cannot divide by 0');
        }
        if ($dividend === '') {
            $dividend = '0';
        }
        if (!is_numeric($dividend)) {
            throw new InvalidArgumentException('The dividend must be a number');
        }
        if (!is_numeric($divisor)) {
            throw new InvalidArgumentException('The divisor must be a number');
        }

        if (is_bool($ownImplementation)) {
            $ownImplementation = $ownImplementation ? self::BCMOD_USE_CUSTOM : 0;
        }

        if (
            (!function_exists('bcmod') && !function_exists('gmp_mod'))
            || $ownImplementation === self::BCMOD_USE_CUSTOM
        ) {
            if (strval(intval($divisor)) !== $divisor) {
                throw new InvalidArgumentException('The custom implementation does not support large numbers in divisor');
            }

            $mod = '';

            foreach (str_split($dividend) as $char) {
                if ($char === '-') {
                    throw new InvalidArgumentException('The custom implementation of bcmod does not support negative numbers');
                }
                $mod = ($mod . $char) % $divisor;
            }

            return (string) $mod;
        } elseif (
            function_exists('gmp_mod')
            && ($ownImplementation === self::BCMOD_USE_GMP || $ownImplementation === 0)
        ) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return gmp_strval(gmp_mod($dividend, $divisor));
        } elseif (
            function_exists('bcmod')
            && ($ownImplementation === self::BCMOD_USE_BCMATH || $ownImplementation === 0)
        ) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return (string) bcmod($dividend, $divisor);
        }

        throw new LogicException('Invalid forced implementation');
    }
}
