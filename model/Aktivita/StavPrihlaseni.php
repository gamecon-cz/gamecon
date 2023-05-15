<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class StavPrihlaseni
{
    public const PRIHLASEN               = 0;
    public const PRIHLASEN_A_DORAZIL     = 1;
    public const DORAZIL_JAKO_NAHRADNIK  = 2;
    public const PRIHLASEN_ALE_NEDORAZIL = 3;
    public const POZDE_ZRUSIL            = 4;
    public const SLEDUJICI               = 5;

    public static function dorazilJakoCokoliv(int $stavPrihlaseni): bool
    {
        switch ($stavPrihlaseni) {
            case self::PRIHLASEN_A_DORAZIL :
            case self::DORAZIL_JAKO_NAHRADNIK :
                return true;
            default:
                return false;
        }
    }

    public static function nedorazilNeboZrusil(int $stavPrihlaseni): bool
    {
        switch ($stavPrihlaseni) {
            case self::PRIHLASEN_ALE_NEDORAZIL :
            case self::POZDE_ZRUSIL :
                return true;
            default:
                return false;
        }
    }

    public static function platiStorno(int $stavPrihlaseni): bool
    {
        return self::nedorazilNeboZrusil($stavPrihlaseni);
    }

    public static function dorazilJakoNahradnik(int $stavPrihlaseni): bool
    {
        return $stavPrihlaseni === self::DORAZIL_JAKO_NAHRADNIK;
    }

    public static function prihlasenJakoSledujici(int $stavPrihlaseni): bool
    {
        return $stavPrihlaseni === self::SLEDUJICI;
    }
}
