<?php declare(strict_types=1);

namespace Gamecon;

class Stat
{
    public const CZ   = 'CZ';
    public const SK   = 'SK';
    public const JINY = null;

    public const CZ_ID   = 1;
    public const SK_ID   = 2;
    public const JINY_ID = -1;

    /** Vrátí kód státu ve formátu ISO 3166-1 alpha-2 https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2 */
    public static function dejKodStatuPodleId(?int $idStatu): ?string
    {
        return match ($idStatu) {
            self::CZ_ID => self::CZ,
            self::SK_ID => self::SK,
            self::JINY_ID, null => self::JINY,
            default => throw new \RuntimeException(
                sprintf("Neznámé id státu v databázi %s", var_export($idStatu, true))
            )
        };
    }
}
