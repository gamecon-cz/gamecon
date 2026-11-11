<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Pravo;

/**
 * Smí přihlášený v adminu aspoň jednu stránku? Kdo ne (účastník bez práv), toho admin odmítne u API i u zvláštních
 * stránek (program účastníka, reporty) a nenabídne mu výběr vzhledu. Omnibox má užší okruh, @see OmniboxAdminu.
 *
 * Rozhoduje seznam práv stránek, ne hlavičky modulů: čtení hlaviček by stálo desítky souborů na každý požadavek API.
 * Že každé právo z hlaviček modulů v seznamu je, hlídá test.
 */
final class PristupDoAdminu
{
    /**
     * Práva, se kterými jde v adminu otevřít aspoň jednu stránku nebo podstránku.
     */
    public const array PRAVA = [
        Pravo::ADMINISTRACE_INFOPULT,
        Pravo::ADMINISTRACE_UBYTOVANI,
        Pravo::ADMINISTRACE_AKCE,
        Pravo::ADMINISTRACE_PREZENCE,
        Pravo::ADMINISTRACE_REPORTY,
        Pravo::ADMINISTRACE_WEB,
        Pravo::ADMINISTRACE_PRAVA,
        Pravo::ADMINISTRACE_STATISTIKY,
        Pravo::ADMINISTRACE_FINANCE,
        Pravo::ADMINISTRACE_MOJE_AKTIVITY,
        Pravo::ADMINISTRACE_NASTAVENI,
        Pravo::ADMINISTRACE_PENIZE,
        Pravo::ADMINISTRACE_WEB_LOGA,
        Pravo::ADMINISTRACE_DEV,
    ];

    /**
     * Zvláštní stránky, které admin ukazuje i nepřihlášenému a účastníkovi bez práv (tabule na infopultu, program
     * na obrazovkách), stránka => důvod.
     */
    public const array VEREJNE_STRANKY = [
        'last-minute-tabule' => 'tabule volných míst na obrazovce u infopultu, bez přihlášení',
        'program-obecny'     => 'program na obrazovkách, bez přihlášení',
    ];

    public static function isPovolen(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null && $prihlaseny->maNekterePravo(self::PRAVA);
    }

    public static function isVerejnaStranka(string $stranka): bool
    {
        return array_key_exists($stranka, self::VEREJNE_STRANKY);
    }
}
