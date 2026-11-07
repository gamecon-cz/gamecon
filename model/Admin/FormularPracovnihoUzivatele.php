<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * Hlídá, že formulář pracovního uživatele (záložka Uživatel, Infopult) uloží změnu tomu, u koho ho organizátor viděl.
 * Pracovní uživatel je jeden na celou session: kdo ho mezitím přepne v jiné záložce prohlížeče, tomu by dříve načtený
 * formulář uložil ubytování, jídlo, prodej, osobní údaje nebo fotku jinému člověku.
 *
 * Formulář se k hlídání přihlásí skrytým polem z @see getSkrytePole(); formulář bez něj se nehlídá.
 */
final class FormularPracovnihoUzivatele
{
    public const string POST_ID = 'idPracovnihoUzivatele';

    /**
     * Skryté pole s ID pracovního uživatele, 0 bez pracovního uživatele (třeba anonymní prodej).
     */
    public static function getSkrytePole(?\Uzivatel $pracovniUzivatel): string
    {
        return sprintf('<input type="hidden" name="%s" value="%d">', self::POST_ID, $pracovniUzivatel?->id() ?? 0);
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
