<?php declare(strict_types=1);

namespace Gamecon\Shop;

class TypPredmetu
{
    public const PREDMET           = 1;
    public const UBYTOVANI         = 2;
    public const TRICKO            = 3;
    public const JIDLO             = 4;
    public const VSTUPNE           = 5;
    public const PARCON            = 6;
    public const PROPLACENI_BONUSU = 7;

    public static function nazevTypu(int $typ, bool $mnozneCislo = false): string
    {
        return match ($typ) {
            self::PREDMET => $mnozneCislo
                ? 'předměty'
                : 'předmět',
            self::UBYTOVANI => 'ubytování',
            self::TRICKO => $mnozneCislo
                ? 'trička'
                : 'tričko',
            self::JIDLO => $mnozneCislo
                ? 'jídla'
                : 'jídlo',
            self::VSTUPNE => 'vstupné',
            self::PARCON => 'parcon',
            self::PROPLACENI_BONUSU => $mnozneCislo
                ? 'proplacení bonusů'
                : 'proplacení bonusu',
            default => $mnozneCislo
                ? 'neznámé typy'
                : 'neznámý typ',
        };
    }
}
