<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Pravo;
use Gamecon\Role\Role;

/**
 * Našeptávač uživatelů v adminu (zvláštní stránka @see STRANKA) vydá jména, e-maily, telefony i zůstatky kohokoli,
 * proto jen tomu, komu ho admin nabízí: výběr uživatele pro práci, reporty a přepnutí na jiného uživatele. Kdo v adminu
 * smí jen jiné stránky (vypravěč s Moje aktivity, správce log), dostane 403.
 */
final class OmniboxAdminu
{
    public const string STRANKA = 'ajax-omnibox';

    /**
     * Práva stránek, které omnibox používají.
     */
    public const array PRAVA = [
        ...PracovniUzivatel::PRAVA,  // výběr uživatele pro práci, i v Infopultu a ve Financích (párování plateb, slučování)
        Pravo::ADMINISTRACE_REPORTY, // omezení reportů na jednoho uživatele
    ];

    /**
     * Infopulťák se smí přepnout jen na vypravěče a partnery (admin/scripts/prihlaseni.php), a jiné mu omnibox nenajde,
     * nemá-li právo, se kterým by mohl hledat kohokoli.
     */
    public const array ROLE_PRO_INFOPULTAKA = [
        Role::LETOSNI_VYPRAVEC,
        Role::LETOSNI_PARTNER,
    ];

    /**
     * Přepnout na jiného uživatele (@see \Uzivatel::muzePrepnoutNaJinehoUzivatele()) smí i ten, kdo žádné z @see PRAVA
     * nemá, a vybírá ho omniboxem.
     */
    public static function isPovolen(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null
            && (self::smiHledatKohokoli($prihlaseny) || $prihlaseny->jeInfopultak());
    }

    /**
     * Role, jejichž nositele omnibox smí najít, nebo null, když kohokoli.
     *
     * @return list<int>|null
     */
    public static function getRoleHledanych(\Uzivatel $prihlaseny): ?array
    {
        return self::smiHledatKohokoli($prihlaseny)
            ? null
            : self::ROLE_PRO_INFOPULTAKA;
    }

    private static function smiHledatKohokoli(\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny->maNekterePravo(self::PRAVA) || $prihlaseny->muzePrepnoutNaJinehoUzivatele();
    }
}
