<?php declare(strict_types=1);

namespace Gamecon;

class Stat
{
    public const CZ = 'CZ';
    public const SK = 'SK';
    public const JINY = null;

    public const CZ_ID = 1;
    public const SK_ID = 2;
    public const JINY_ID = -1;

    /** Vrátí kód státu ve formátu ISO 3166-1 alpha-2 https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2 */
    public static function dejKodStatuPodleId(?int $idStatu): ?string {
        switch ($idStatu) {
            case self::CZ_ID :
                return self::CZ;
            case self::SK_ID :
                return self::SK;
            case self::JINY_ID :
                return self::JINY;
            default :
                throw new \RuntimeException(
                    sprintf("Neznámé id státu v databázi '%s'", var_export($idStatu, true))
                );
        }
    }
}
