<?php declare(strict_types=1);

namespace Gamecon\Shop;

class TypPredmetu
{
    public const PREDMET = 1;
    public const UBYTOVANI = 2;
    public const TRICKO = 3;
    public const JIDLO = 4;
    public const VSTUPNE = 5;
    public const PARCON = 6;
    public const PROPLACENI_BONUSU = 7;

    public static function nazevTypu(int $typ, bool $mnozneCislo = false) {
        switch ($typ) {
            case self::PREDMET :
                return $mnozneCislo
                    ? 'předměty'
                    : 'předmět';
            case self::UBYTOVANI :
                return 'ubytování';
            case self::TRICKO :
                return $mnozneCislo
                    ? 'trička'
                    : 'tričko';
            case self::JIDLO :
                return $mnozneCislo
                    ? 'jídla'
                    : 'jídlo';
            case self::VSTUPNE :
                return 'vstupné';
            case self::PARCON :
                return 'parcon';
            case self::PROPLACENI_BONUSU :
                return $mnozneCislo
                    ? 'proplacení bonusů'
                    : 'proplacení bonusu';
            default :
                return $mnozneCislo
                    ? 'neznámé typy'
                    : 'neznámý typ';
        }
    }
}
