<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Pravo;

/**
 * Pracovní uživatel je účastník, se kterým organizátor v adminu pracuje: moduly adminu mu ukládají platby, ubytování,
 * role nebo osobní údaje a admin API za něj přihlašuje na aktivity a spravuje tým, jako by byl přihlášený on.
 *
 * Vybrat si ho a pracovat s ním smí jen přihlášený s právem některé stránky, která s pracovním uživatelem pracuje
 * (@see PRAVA). Stejnému okruhu admin nabízí výběr uživatele. Ostatním se výběr nezpracuje a pracovní uživatel ze
 * session se zahodí, viz admin/scripts/prihlaseni.php. Pracovního uživatele čti jen přes @see zSession(), ne přímo
 * ze session: tak platí to pravidlo i tam, kam prihlaseni.php nedosáhne.
 */
final class PracovniUzivatel
{
    /**
     * Práva stránek adminu, které s pracovním uživatelem pracují a bez něj nemají smysl.
     */
    public const array PRAVA = [
        Pravo::ADMINISTRACE_INFOPULT,  // Infopult, program za účastníka v admin API
        Pravo::ADMINISTRACE_UBYTOVANI, // Uživatel, kam vedou odkazy ?pracovni_uzivatel= z e-mailů a reportů
        Pravo::ADMINISTRACE_PRAVA,     // Práva
        Pravo::ADMINISTRACE_FINANCE,   // Finance
        Pravo::ADMINISTRACE_PENIZE,    // Peníze
    ];

    /**
     * Právo jednat za pracovního uživatele v programu: přihlašovat ho na aktivity a spravovat jeho tým (admin API
     * aktivitaAkce a aktivitaTym), s příznaky operátora, třeba na dosud neotevřené aktivity. Užší než @see PRAVA:
     * Uživatel, Práva, Finance a Peníze pracovního uživatele potřebují, přihlašovat ho na aktivity ale nemají.
     */
    public const int PRAVO_PROGRAMU = Pravo::ADMINISTRACE_INFOPULT;

    public static function isPovolen(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null && $prihlaseny->maNekterePravo(self::PRAVA);
    }

    /**
     * Smí přihlášený za pracovního uživatele přihlašovat na aktivity a spravovat jeho tým? @see PRAVO_PROGRAMU
     */
    public static function isPovolenProgram(?\Uzivatel $prihlaseny): bool
    {
        return $prihlaseny !== null && $prihlaseny->maPravo(self::PRAVO_PROGRAMU);
    }

    /**
     * Pracovní uživatel ze session, jen pro toho, kdo ho smí mít.
     */
    public static function zSession(?\Uzivatel $prihlaseny): ?\Uzivatel
    {
        return self::isPovolen($prihlaseny)
            ? \Uzivatel::zSession(\Uzivatel::UZIVATEL_PRACOVNI)
            : null;
    }

    /**
     * ID uživatele z formuláře nebo z odkazu; null, když chybí nebo to není kladné celé číslo (třeba pole z
     * `?pracovni_uzivatel[]=`).
     */
    public static function parseId(mixed $hodnota): ?int
    {
        if (! is_int($hodnota) && ! is_string($hodnota)) {
            return null;
        }
        $id = filter_var($hodnota, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false
            ? null
            : $id;
    }
}
