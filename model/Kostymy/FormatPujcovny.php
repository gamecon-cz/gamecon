<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Cas\DateTimeCz;

/**
 * Jednotné vypisování časů a jmen na stránkách půjčovny (admin i web).
 */
final class FormatPujcovny
{
    /**
     * „čtvrtek 10:00–14:00“, přes půlnoc „čtvrtek 22:00 – pátek 2:00“
     */
    public static function casAktivity(?string $zacatek, ?string $konec): string
    {
        if ($zacatek === null || $konec === null) {
            return 'bez času';
        }
        $od = new DateTimeCz($zacatek);
        $do = new DateTimeCz($konec);

        return $od->format('Y-m-d') === $do->format('Y-m-d')
            ? $od->format('l G:i') . '–' . $do->format('G:i')
            : $od->format('l G:i') . ' – ' . $do->format('l G:i');
    }

    public static function denACas(?string $kdy): string
    {
        return $kdy === null
            ? ''
            : (new DateTimeCz($kdy))->format('l G:i');
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, \Uzivatel> podle ID
     */
    public static function uzivatelePodleId(array $ids): array
    {
        $uzivatele = [];
        foreach (\Uzivatel::zIds(array_values(array_unique(array_map('intval', $ids)))) as $uzivatel) {
            $uzivatele[(int) $uzivatel->id()] = $uzivatel;
        }

        return $uzivatele;
    }

    public static function jmeno(?\Uzivatel $uzivatel): string
    {
        return $uzivatel === null
            ? '(smazaný účet)'
            : (string) $uzivatel->jmenoNick();
    }
}
