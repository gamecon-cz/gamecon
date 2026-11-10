<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * One form of a lecture title, for the spreadsheet import and for the titles taken over from the program.
 */
final class NazevPrednasky
{
    /**
     * Without HTML entities and invisible characters (titles of old years in the program carry `&shy;`, soft hyphens
     * and zero-width spaces), whitespace squeezed, and a spaced hyphen or em dash turned into the Czech spaced en dash
     * (záměr: the program itself mixes „Mistrovství DrD - …“ and „Mistrovství DrD – …“). A hyphen inside a word stays.
     */
    public static function uprav(string $nazev): string
    {
        $nazev = html_entity_decode($nazev, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $nazev = str_replace(["\u{00AD}", "\u{200B}"], '', $nazev);
        $nazev = trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $nazev));

        return (string) preg_replace('~ [-\x{2014}] ~u', ' – ', $nazev);
    }
}
