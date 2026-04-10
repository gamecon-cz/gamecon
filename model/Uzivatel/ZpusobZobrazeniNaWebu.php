<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

class ZpusobZobrazeniNaWebu
{
    public const POUZE_PREZDIVKA = 0;
    public const JMENO_A_PRIJMENI = 1;
    public const JMENO_S_PREZDIVKOU_A_PRIJMENI = 2;

    public static function vychozi(): int
    {
        return self::POUZE_PREZDIVKA;
    }

    public static function jePlatny(int $zpusobZobrazeniNaWebu): bool
    {
        return in_array(
            $zpusobZobrazeniNaWebu,
            [
                self::POUZE_PREZDIVKA,
                self::JMENO_A_PRIJMENI,
                self::JMENO_S_PREZDIVKOU_A_PRIJMENI,
            ],
            true,
        );
    }
}
