<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class StavPrihlaseni
{
    public const NEPRIHLASEN = -1;
    // akce_prihlaseni
    public const PRIHLASEN              = 0;
    public const PRIHLASEN_A_DORAZIL    = 1;
    public const DORAZIL_JAKO_NAHRADNIK = 2;
    // akce_prihlaseni_spec
    public const PRIHLASEN_ALE_NEDORAZIL = 3; // storno poplatek 100%, viz SELECT platba_procent FROM akce_prihlaseni_stavy WHERE nazev = 'nedorazil'
    public const POZDE_ZRUSIL            = 4; // storno poplatek 50%, viz SELECT platba_procent FROM akce_prihlaseni_stavy WHERE nazev = 'pozdě zrušil'
    public const SLEDUJICI               = 5;

    public static function dorazilJakoCokoliv(int $stavPrihlaseni): bool
    {
        return match ($stavPrihlaseni) {
            self::PRIHLASEN_A_DORAZIL, self::DORAZIL_JAKO_NAHRADNIK => true,
            default => false,
        };
    }

    public static function nedorazilNeboZrusil(int $stavPrihlaseni): bool
    {
        return match ($stavPrihlaseni) {
            self::PRIHLASEN_ALE_NEDORAZIL, self::POZDE_ZRUSIL => true,
            default => false,
        };
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

    /**
     * Vrátí řetězcový kód používaný frontendem (pole `stavPrihlaseni` v JSON
     * odpovědích). Pro neznámé / NEPRIHLASEN vrací null — to mapuje na
     * `stavPrihlaseni: null` v TypeScriptu (`StavPřihlášení | null`).
     */
    public static function frontendKod(int $stavPrihlaseni): ?string
    {
        return match ($stavPrihlaseni) {
            self::PRIHLASEN               => 'prihlasen',
            self::PRIHLASEN_A_DORAZIL     => 'prihlasenADorazil',
            self::DORAZIL_JAKO_NAHRADNIK  => 'dorazilJakoNahradnik',
            self::PRIHLASEN_ALE_NEDORAZIL => 'prihlasenAleNedorazil',
            self::POZDE_ZRUSIL            => 'pozdeZrusil',
            self::SLEDUJICI               => 'sledujici',
            default                       => null,
        };
    }
}
