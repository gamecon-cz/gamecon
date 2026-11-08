<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * Hlídá, že formulář pracovního uživatele (záložka Uživatel, Infopult) uloží změnu tomu, u koho ho organizátor viděl.
 * Pracovní uživatel je jeden na celou session: kdo ho mezitím přepne v jiné záložce prohlížeče, tomu by dříve načtený
 * formulář připsal platbu, ubytování, jídlo, prodej, osobní údaje nebo fotku jinému člověku.
 *
 * Stránka, která s pracovním uživatelem pracuje, prožene svůj výstup přes @see doplnDoFormularu(), a hlídaný je tak
 * každý její POST formulář, i ten, který na ni přibude později. Formulář bez skrytého pole se nehlídá: ostatním
 * stránkám adminu by přepnutí pracovního uživatele jen zahodilo rozepsaný formulář, který s ním nesouvisí.
 */
final class FormularPracovnihoUzivatele
{
    public const string POST_ID = 'idPracovnihoUzivatele';

    /**
     * Skryté pole s ID pracovního uživatele, 0 bez pracovního uživatele (třeba anonymní prodej).
     *
     * @param string $idFormulare pole mimo formulář, k němuž patří (atribut `form`), pro formulář, jehož obsah
     *                            přepisuje skript
     */
    public static function getSkrytePole(?\Uzivatel $pracovniUzivatel, string $idFormulare = ''): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%d"%s>',
            self::POST_ID,
            $pracovniUzivatel?->id() ?? 0,
            $idFormulare !== ''
                ? ' form="' . htmlspecialchars($idFormulare) . '"'
                : '',
        );
    }

    /**
     * Doplní skryté pole z @see getSkrytePole() na začátek každého POST formuláře v HTML stránky.
     */
    public static function doplnDoFormularu(string $html, ?\Uzivatel $pracovniUzivatel): string
    {
        $pole = self::getSkrytePole($pracovniUzivatel);

        return preg_replace_callback(
            // celá značka <form …>, i když je v hodnotě atributu znak > (třeba onsubmit s porovnáním)
            '~<form\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>~i',
            static fn (array $znacka): string => preg_match('~(?<![\w-])method\s*=\s*["\']?post\b~i', $znacka[0]) === 1
                ? $znacka[0] . $pole
                : $znacka[0],
            $html,
        ) ?? throw new \RuntimeException('Formuláře stránky se nepodařilo projít: ' . preg_last_error_msg());
    }

    /**
     * Odmítne POST, který nese ID jiného pracovního uživatele než současného: ohlásí chybu a skončí.
     *
     * @param array<string, mixed> $post
     */
    public static function hlidej(array $post, ?\Uzivatel $pracovniUzivatel): void
    {
        if (!array_key_exists(self::POST_ID, $post)) {
            return;
        }
        if ((int) $post[self::POST_ID] === ($pracovniUzivatel?->id() ?? 0)) {
            return;
        }
        chyba('Nic se nezměnilo, formulář patřil jinému uživateli, než se kterým teď pracuješ. Pracovní uživatel se nejspíš mezitím přepnul v jiné záložce prohlížeče. Zkontroluj, s kým pracuješ, a pošli formulář znovu.');
    }
}
