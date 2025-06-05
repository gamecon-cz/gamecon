<?php
declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\DuplicatedUnifiedKeyException;

class ImportKeyUnifier
{
    public const UNIFY_UP_TO_WHITESPACES         = 1;
    public const UNIFY_UP_TO_CASE                = 2;
    public const UNIFY_UP_TO_SPACES              = 3;
    public const UNIFY_UP_TO_DIACRITIC           = 4;
    public const UNIFY_UP_TO_WORD_CHARACTERS     = 5;
    public const UNIFY_UP_TO_NUMBERS_AND_LETTERS = 6;
    public const UNIFY_UP_TO_LETTERS             = 7;

    public static function parseId(string $value): ?int
    {
        $id = preg_match('~\D~', trim($value))
            ? null
            : (int)$value;

        return $id !== null && $id > 0
            ? $id
            : null;
    }

    public static function toUnifiedKey(
        string $value,
        array  $occupiedKeys,
        int    $unifyDepth = self::UNIFY_UP_TO_NUMBERS_AND_LETTERS,
    ): string {
        $unifiedKey = self::createUnifiedKey($value, $unifyDepth);
        if (in_array($unifiedKey, $occupiedKeys, true)) {
            throw new DuplicatedUnifiedKeyException(
                sprintf(
                    "Can not create unified key from '%s' as resulting key '%s' using unify depth %d already exists. Existing keys: %s",
                    $value,
                    $unifiedKey,
                    $unifyDepth,
                    implode(';', array_map(static function (
                        string $occupiedKey,
                    ) {
                        return "'$occupiedKey'";
                    }, $occupiedKeys)),
                ),
                $unifiedKey,
            );
        }

        return $unifiedKey;
    }

    private static function createUnifiedKey(
        string $value,
        int    $depth,
    ): string {
        if ($depth <= 0) {
            return $value;
        }
        $value = preg_replace('~\s+~', ' ', $value);
        if ($depth === self::UNIFY_UP_TO_WHITESPACES) {
            return $value;
        }
        $value = mb_strtolower($value, 'UTF-8');
        if ($depth === self::UNIFY_UP_TO_CASE) {
            return $value;
        }
        $value = (string)str_replace(' ', '', $value);
        if ($depth === self::UNIFY_UP_TO_SPACES) {
            return $value;
        }
        $value = odstranDiakritiku($value);
        if ($depth === self::UNIFY_UP_TO_DIACRITIC) {
            return $value;
        }
        $value = preg_replace('~\W~u', '', $value);
        if ($depth === self::UNIFY_UP_TO_WORD_CHARACTERS) {
            return $value;
        }
        $value = preg_replace('~[^a-z0-9]~', '', $value);
        if ($depth === self::UNIFY_UP_TO_NUMBERS_AND_LETTERS) {
            return $value;
        }
        $value = preg_replace('~[^a-z]~', '', $value);
        if ($depth === self::UNIFY_UP_TO_LETTERS) {
            return $value;
        }

        return $value;
    }
}
