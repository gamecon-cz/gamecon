<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Pravo;

/**
 * Smí přihlášený v adminu aspoň jednu stránku? Kdo ne (účastník bez práv), toho admin odmítne u API i u zvláštních
 * stránek (program účastníka, reporty) a nenabídne mu výběr vzhledu. Omnibox má užší okruh, @see OmniboxAdminu.
 * Stránka z @see PRAVA_JEN_STRANKY do adminu pustí, ale API a zvláštní stránky neotevře.
 *
 * Rozhoduje seznam práv stránek, ne hlavičky modulů: čtení hlaviček by stálo desítky souborů na každý požadavek API.
 * Že každé právo z hlaviček modulů v jednom ze seznamů je, hlídá test.
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
        Pravo::ADMINISTRACE_PUJCOVNA_KOSTYMU,
        Pravo::ADMINISTRACE_PUSH_NOTIFIKACE,
    ];

    /**
     * Práva stránek, které admin API ani zvláštní stránky nepotřebují, právo => proč. Kdo má jen je, otevře svou
     * stránku a vybere si vzhled, ale API a zvláštní stránky (interní program, reporty) ho odmítnou.
     */
    public const array PRAVA_JEN_STRANKY = [
        Pravo::ADMINISTRACE_BINGO => 'Bingo mají všichni orgové včetně pauzujícího full-orga, který jinak v adminu nic'
            . ' nesmí. Stav si stránka bere sama od sebe (bingo?stav=1), API nepotřebuje.',
        Pravo::PROVADI_KOREKCE => 'Korektury textů aktivit jsou obyčejné formuláře a stažení souboru, API'
            . ' nepotřebují. Korektor má navíc správu aktivit, se kterou smí víc.',
        Pravo::SCHVALOVANI_MEDAILONKU => 'Schvalování medailonků mají všichni orgové včetně pauzujícího full-orga a mini-orga;'
            . ' stránka je obyčejný formulář, API nepotřebuje.',
    ];

    /**
     * Zvláštní stránky, které admin ukazuje i nepřihlášenému a účastníkovi bez práv (tabule na infopultu, program
     * na obrazovkách), stránka => důvod.
     */
    public const array VEREJNE_STRANKY = [
        'last-minute-tabule' => 'tabule volných míst na obrazovce u infopultu, bez přihlášení',
        'program-obecny'     => 'program na obrazovkách, bez přihlášení',
    ];

    /**
     * Smí admin API a zvláštní stránky.
     */
    public static function isPovolen(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null && $prihlaseny->maNekterePravo(self::PRAVA);
    }

    /**
     * Smí v adminu aspoň jednu stránku, třeba jen tu z @see PRAVA_JEN_STRANKY.
     */
    public static function isPovolenaStranka(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null
            && $prihlaseny->maNekterePravo([...self::PRAVA, ...array_keys(self::PRAVA_JEN_STRANKY)]);
    }

    public static function isVerejnaStranka(string $stranka): bool
    {
        return array_key_exists($stranka, self::VEREJNE_STRANKY);
    }
}
