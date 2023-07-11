<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use Granam\RemoveDiacritics\RemoveDiacritics;

class KategoriePredmetu
{
    public const PLACKA     = 1;
    public const KOSTKA     = 2;
    public const TRICKO     = 3; // se slevou viz shop_predmety.se_slevou
    public const TILKO      = 4; // se slevou viz shop_predmety.se_slevou
    public const BLOK       = 5;
    public const NICKNACK   = 6;
    public const PONOZKY    = 7;
    public const COVID_TEST = 8;
    public const TASKA      = 10;

    public static function kategoriePodleNazvu(string $nazev): ?int
    {
        $nazevZjednoduseny = RemoveDiacritics::toConstantLikeValue($nazev);
        if (stripos($nazevZjednoduseny, 'placka') !== false) {
            return self::PLACKA;
        }
        if (stripos($nazevZjednoduseny, 'kostka') !== false) {
            return self::KOSTKA;
        }
        if (stripos($nazevZjednoduseny, 'tricko') !== false) {
            return self::TRICKO;
        }
        if (stripos($nazevZjednoduseny, 'tilko') !== false) {
            return self::TILKO;
        }
        if (stripos($nazevZjednoduseny, 'blok') !== false) {
            return self::BLOK;
        }
        if (stripos($nazevZjednoduseny, 'nicknack') !== false) {
            return self::NICKNACK;
        }
        if (stripos($nazevZjednoduseny, 'ponozky') !== false) {
            return self::PONOZKY;
        }
        if (stripos($nazevZjednoduseny, 'covid') !== false) {
            return self::COVID_TEST;
        }
        if (stripos($nazevZjednoduseny, 'taska') !== false) {
            return self::TASKA;
        }
        return null;
    }
}
