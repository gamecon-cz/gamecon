<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Pravo;

/**
 * "Přihlásit se jako" in the admin: whoever sits at the device goes on as another user, typically an infopult
 * member filling in the attendance of a storyteller. The session remembers who switched, so that they can go back
 * to their own account, logging out ends their own login and not the other user's (@see \Uzivatel::odhlas()),
 * and the look of the admin is not chosen for the other user (@see \Gamecon\Admin\VzhledAdminu).
 *
 * @see docs/generated/prepnuti-na-uzivatele.md
 */
class PrepnutiNaUzivatele
{
    /**
     * A GET parameter, not a form: the online attendance (Moje aktivity), where the infopult member ends up, has
     * only links. Going back to oneself needs no protection against a forged request.
     */
    public const string PARAMETR_ZPET = 'zpetZPrepnuti';

    public const string NESMI_PREPINAT = 'Na jiného uživatele se přepnout nemůžeš.';

    private const string SESSION_KLIC = 'prepnuto_z_uzivatele';

    /**
     * The login stamp the session of whoever switched had (@see \Uzivatel::getRazitkoPrihlaseniZSession()). While
     * switched, the session carries the stamp of the other user, so \Uzivatel::zSession() checks this one as well:
     * logging out of other devices by whoever switched ends the switched session too. Going back checks it again.
     */
    private const string SESSION_KLIC_RAZITKA = 'prepnuto_z_razitka';

    /**
     * Rights of a storyteller or a partner the infopult member may take over by switching to them: the attendance
     * in Moje aktivity, which is what the switch is for, and the perks of their own account and marks in listings,
     * which give nothing over other users or the festival. Any other right the infopult member does not hold
     * (an admin panel, switching to anyone, cancelling purchases, ...) blocks the switch, a right added later too
     * until it is listed here.
     */
    private const array PRAVA_KTERA_INFOPULTAK_SMI_PREVZIT = [
        Pravo::PORADANI_AKTIVIT,
        Pravo::ADMINISTRACE_MOJE_AKTIVITY,
        Pravo::PREKRYVANI_AKTIVIT,
        Pravo::PLNY_SERVIS,
        Pravo::PLACKA_ZDARMA,
        Pravo::KOSTKA_ZDARMA,
        Pravo::JIDLO_SE_SLEVOU,
        Pravo::JIDLO_ZDARMA,
        Pravo::UBYTOVANI_ZDARMA,
        Pravo::MODRE_TRICKO_ZDARMA,
        Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA,
        Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY,
        Pravo::UBYTOVANI_NEDELNI_NOC_ZDARMA,
        Pravo::CASTECNA_SLEVA_NA_AKTIVITY,
        Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA,
        Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA,
        Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA,
        Pravo::AKTIVITY_ZDARMA,
        Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI,
        Pravo::VYPISOVAT_V_REPORTU_NEUBYTOVANYCH,
        Pravo::TITUL_ORGANIZATOR,
        Pravo::UNIKATNI_ROLE,
        Pravo::BEZ_BONUSU_ZA_VEDENI_AKTIVIT,
        Pravo::UBYTOVANI_CTVRTECNI_NOC_ZDARMA,
        Pravo::UBYTOVANI_PATECNI_NOC_ZDARMA,
        Pravo::UBYTOVANI_SOBOTNI_NOC_ZDARMA,
        Pravo::JAKEKOLIV_TRICKO_ZDARMA,
        Pravo::UBYTOVANI_NEDELNI_NOC_NABIZET,
        Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC,
        Pravo::JEDNA_AKTIVITA_ZDARMA,
    ];

    /**
     * Without a running session (CLI) nothing is remembered.
     */
    public static function zSession(): self
    {
        if (! isset($_SESSION)) {
            $_SESSION = [];
        }

        return new self($_SESSION);
    }

    /**
     * @param array<string, mixed> $session the PHP session, by reference so that the switch outlives the request
     */
    public function __construct(
        private array &$session,
    ) {
    }

    /**
     * Whether $kdo gets the switch at all: anyone with the right (@see \Uzivatel::muzePrepnoutNaJinehoUzivatele()),
     * an infopult member only to some users (@see getDuvodOdmitnutiInfopultaka()).
     */
    public static function smiPrepinat(\Uzivatel $kdo): bool
    {
        return $kdo->muzePrepnoutNaJinehoUzivatele() || $kdo->jeInfopultak();
    }

    /**
     * @return string|null HTML, why $kdo may not switch to $naKoho, null when they may
     */
    public function getDuvodOdmitnuti(\Uzivatel $kdo, \Uzivatel $naKoho): ?string
    {
        if (! self::smiPrepinat($kdo)) {
            return self::NESMI_PREPINAT;
        }
        if ($kdo->muzePrepnoutNaJinehoUzivatele()) {
            return null;
        }

        return self::getDuvodOdmitnutiInfopultaka($kdo, $naKoho);
    }

    /**
     * An infopult member switches to a storyteller or a partner to fill in the attendance for them, and must not
     * gain anything else by it (@see PRAVA_KTERA_INFOPULTAK_SMI_PREVZIT).
     * On locale everyone may switch to anyone (@see \Uzivatel::muzePrepnoutNaJinehoUzivatele()), so this is never
     * reached there through getDuvodOdmitnuti(), not in tests either, and the tests call it directly.
     *
     * @return string|null HTML, why the infopult member may not switch to $naKoho, null when they may
     */
    public static function getDuvodOdmitnutiInfopultaka(\Uzivatel $infopultak, \Uzivatel $naKoho): ?string
    {
        $jmeno = htmlspecialchars($naKoho->jmenoNick());
        if (! $naKoho->jeVypravec() && ! $naKoho->jePartner()) {
            return "Přepnout se můžeš jen na vypravěče nebo partnera, {$jmeno} není ani jedno.";
        }
        // záporná práva jsou značky přihlášení a přítomnosti starých ročníků, 0 je role bez práv
        $pravaNavic = array_diff(
            array_filter($naKoho->prava(), static fn (int $pravo): bool => $pravo > 0),
            $infopultak->prava(),
            self::PRAVA_KTERA_INFOPULTAK_SMI_PREVZIT,
        );
        if ($pravaNavic === []) {
            return null;
        }
        $nazvyPrav = dbOneArray(<<<'SQL'
SELECT jmeno_prava FROM r_prava_soupis WHERE id_prava IN ($0) ORDER BY id_prava
SQL,
            [
                0 => array_values($pravaNavic),
            ],
        );

        return "Na {$jmeno} se přepnout nemůžeš, smí víc než vypravěč: "
               . htmlspecialchars(implode(', ', $nazvyPrav)) . '.';
    }

    /**
     * Logs in $naKoho in place of $kdo. The JWT stays the one of whoever sits at the device: a JWT of $naKoho would
     * overwrite the one $naKoho has on their own devices.
     *
     * @return \Uzivatel|null null when switching back to oneself after logging out of this device elsewhere
     */
    public function prepni(\Uzivatel $kdo, \Uzivatel $naKoho): ?\Uzivatel
    {
        // kdo se přepíná dál, vrací se pořád k tomu, kdo u zařízení sedí
        $idPuvodniho = $this->getIdPuvodnihoUzivatele();
        if ($idPuvodniho === null) {
            $idPuvodniho = $kdo->id();
            $razitkoPuvodniho = \Uzivatel::getRazitkoPrihlaseniZSession();
        } else {
            $razitkoPuvodniho = $this->getRazitkoPuvodnihoUzivatele();
        }
        if ($naKoho->id() === $idPuvodniho) {
            $this->zapomen();

            return \Uzivatel::prihlasIdSRazitkem($idPuvodniho, $razitkoPuvodniho, vytvoritJwt: false);
        }
        $prihlaseny = \Uzivatel::prihlasId($naKoho->id(), vytvoritJwt: false)
                      ?? throw new \RuntimeException("Uživatel {$naKoho->id()} zmizel během přepnutí");
        $this->zaznamenej($idPuvodniho, $razitkoPuvodniho);

        return $prihlaseny;
    }

    /**
     * @return \Uzivatel|null whoever switched, logged in again, null when nobody did or when they logged out of
     *                        this device elsewhere in the meantime (the session is then dropped)
     */
    public function vratSe(): ?\Uzivatel
    {
        $idPuvodniho = $this->getIdPuvodnihoUzivatele();
        if ($idPuvodniho === null) {
            return null;
        }
        $razitkoPuvodniho = $this->getRazitkoPuvodnihoUzivatele();
        $this->zapomen();

        return \Uzivatel::prihlasIdSRazitkem($idPuvodniho, $razitkoPuvodniho);
    }

    public function isPrepnuto(): bool
    {
        return $this->getIdPuvodnihoUzivatele() !== null;
    }

    public function getIdPuvodnihoUzivatele(): ?int
    {
        $id = $this->session[self::SESSION_KLIC] ?? null;

        return $id !== null
            ? (int) $id
            : null;
    }

    /**
     * @return string|null the login stamp whoever switched logged in with, null when they had none or nobody switched
     */
    public function getRazitkoPuvodnihoUzivatele(): ?string
    {
        return $this->session[self::SESSION_KLIC_RAZITKA] ?? null;
    }

    /**
     * @return \Uzivatel|null whoever sits at the device after switching, null when nobody switched
     */
    public function getPuvodniUzivatel(): ?\Uzivatel
    {
        $idPuvodniho = $this->getIdPuvodnihoUzivatele();

        return $idPuvodniho !== null
            ? \Uzivatel::zId($idPuvodniho)
            : null;
    }

    /**
     * @return array{url: string, nazev: string}|null the link back to the own account, the name is not escaped
     */
    public function getOdkazZpet(string $zakladniAdminUrl = URL_ADMIN): ?array
    {
        $puvodni = $this->getPuvodniUzivatel();
        if ($puvodni === null) {
            return null;
        }

        return [
            'url'   => $zakladniAdminUrl . '/?' . http_build_query([self::PARAMETR_ZPET => 1]),
            'nazev' => 'zpět na ' . $puvodni->jmenoNick(),
        ];
    }

    /**
     * For a session that goes on after the logged-in user was reloaded (@see \Uzivatel::otoc()).
     */
    public function zaznamenej(int $idPuvodnihoUzivatele, ?string $razitkoPuvodnihoUzivatele = null): void
    {
        $this->session[self::SESSION_KLIC] = $idPuvodnihoUzivatele;
        $this->session[self::SESSION_KLIC_RAZITKA] = $razitkoPuvodnihoUzivatele;
    }

    /**
     * A new login (a password, the permanent login) starts without a switch.
     */
    public function zapomen(): void
    {
        unset($this->session[self::SESSION_KLIC], $this->session[self::SESSION_KLIC_RAZITKA]);
    }
}
