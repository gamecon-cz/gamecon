<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

enum ZpusobZobrazeniNaWebu: int
{
    case POUZE_PREZDIVKA                = 0;
    case JMENO_A_PRIJMENI               = 1;
    case JMENO_S_PREZDIVKOU_A_PRIJMENI  = 2;

    public static function vychozi(): self
    {
        return self::POUZE_PREZDIVKA;
    }

    public static function zHodnoty(int|string|null $hodnota): self
    {
        if ($hodnota === null || $hodnota === '') {
            return self::vychozi();
        }

        return self::tryFrom((int) $hodnota) ?? self::vychozi();
    }

    public function popis(): string
    {
        return match ($this) {
            self::POUZE_PREZDIVKA                => 'Pouze přezdívka',
            self::JMENO_A_PRIJMENI               => 'Jméno + příjmení',
            self::JMENO_S_PREZDIVKOU_A_PRIJMENI  => 'Jméno + přezdívka + příjmení',
        };
    }

    /**
     * @return array<int,string> Hodnota (int) => popis pro vykreslení <select>.
     */
    public static function proSelect(): array
    {
        $vysledek = [];
        foreach (self::cases() as $pripad) {
            $vysledek[$pripad->value] = $pripad->popis();
        }

        return $vysledek;
    }

    /**
     * @return int[] Všechny platné int hodnoty.
     */
    public static function platneHodnoty(): array
    {
        return array_map(static fn (self $pripad) => $pripad->value, self::cases());
    }
}
