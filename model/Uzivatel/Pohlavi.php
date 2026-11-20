<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

class Pohlavi
{
    public const ZENA_KOD = 'f';
    public const MUZ_KOD  = 'm';

    public static function seznamProSelect(): array
    {
        return [
            self::ZENA_KOD => \t('ucet.pohlavi.zena'),
            self::MUZ_KOD  => \t('ucet.pohlavi.muz'),
        ];
    }

    /** Vrátí koncovku "a" pro holky (resp. "" pro kluky) */
    public static function koncovkaDlePohlavi(string $kodPohlavi, $koncovkaProZeny = 'a'): string
    {
        return ($kodPohlavi === self::ZENA_KOD)
            ? $koncovkaProZeny
            : '';
    }
}
