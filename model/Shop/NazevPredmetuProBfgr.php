<?php

declare(strict_types=1);

namespace Gamecon\Shop;

/**
 * BFGR matches a purchase to its column by name, so both sides must name an item the same way.
 * A column list and a participant's purchases each go through here.
 */
final class NazevPredmetuProBfgr
{
    /**
     * Last year's model gets its year so it does not merge with this year's, unless its name
     * already ends with it — past dice are named "Kostka 2018".
     */
    public static function sRokemModelu(string $nazev, ?int $modelRok, int $rocnik): string
    {
        $nazev = trim($nazev);
        if ($modelRok === null || $modelRok === $rocnik || str_ends_with($nazev, ' ' . $modelRok)) {
            return $nazev;
        }

        return $nazev . ' ' . $modelRok;
    }

    /**
     * Every die column carries a year, this year's included.
     */
    public static function kostkaSRokem(string $nazev, ?int $modelRok, int $rocnik): string
    {
        $nazev = self::sRokemModelu($nazev, $modelRok, $rocnik);

        return preg_match('~ \d{4}$~', $nazev) === 1 ? $nazev : $nazev . ' ' . $rocnik;
    }
}
