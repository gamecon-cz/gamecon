<?php

declare(strict_types=1);

use Gamecon\Admin\FormularPracovnihoUzivatele;
use Gamecon\Admin\PracovniUzivatel;
use Gamecon\Dev\NavratNaPreview;
use Gamecon\Dev\SsoPrihlaseniZUrl;
use Gamecon\Exceptions\UzivatelNenalezen;
use Gamecon\Login\Login;
use Gamecon\Uzivatel\PrepnutiNaUzivatele;

/*
 * Kód starající o přihlášení uživatele a výběr uživatele pro práci
 */
// Přihlášení. Zpět na preview, odkud brána preview poslala do adminu, se jde přes ?returnTo=; formulář se odesílá
// na tutéž URL, takže parametr přežije odeslání, a chyba() po špatném hesle vrací na ni (back()) také.
$u = null;
if (post(Login::LOGIN_INPUT_NAME) && post(Login::PASSWORD_INPUT_NAME)) {
    $pravePrihlaseny = Uzivatel::prihlas(post(Login::LOGIN_INPUT_NAME), post(Login::PASSWORD_INPUT_NAME));
    if (! $pravePrihlaseny) {
        chyba('Chybné přihlašovací jméno nebo heslo');
    }
    back(NavratNaPreview::getBezpecnaUrl(get(NavratNaPreview::PARAMETR)));
}
$u = Uzivatel::zSession();

// Magické přihlášení z administračního rozcestníku (?gcsso= token). Kdy přihlásí, drží ArchivSsoPrihlaseni,
// výběr klíče a odstranění tokenu z URL SsoPrihlaseniZUrl (společná s webem).
// Viz docs/generated/odkazy-do-archivnich-rocniku.md.
SsoPrihlaseniZUrl::zpracuj($u);

// Už přihlášený, kdo otevřel odkaz z brány preview, se vrací hned; brána mu ho ukazuje jen tehdy, když ho nezná.
$navratPrihlaseneho = NavratNaPreview::getCilProPrihlaseneho(
    (bool) $u,
    $_SERVER['REQUEST_METHOD'] ?? '',
    get(NavratNaPreview::PARAMETR),
);
if ($navratPrihlaseneho !== null) {
    back($navratPrihlaseneho);
}

// kdo se v adminu přepnul na jiného uživatele („Přihlásit se jako“, viz níže), používá i admin/index.php
$prepnutiNaUzivatele = new PrepnutiNaUzivatele($_SESSION);
if (get(PrepnutiNaUzivatele::PARAMETR_ZPET) !== null) {
    $prepnutiNaUzivatele->vratSe();
    // úvod adminu pošle vráceného na jeho úvodní stránku
    back(URL_ADMIN);
}

if (post('odhlasNAdm')) {
    if ($u) {
        $u->odhlas(false);
    }
    back();
}

// Uživatel pro práci, jen pro toho, komu ho admin nabízí, viz PracovniUzivatel. Kdo ho mít nesmí, tomu se klíč ze
// session zahodí, i ten nastavený před přihlášením nebo před ztrátou práva, aby se po dalším přihlášení nevrátil.
$uPracovni = PracovniUzivatel::zSession($u);
if (! PracovniUzivatel::isPovolen($u)) {
    Uzivatel::odhlasKlic(Uzivatel::UZIVATEL_PRACOVNI);
}

$ulozPredchozihoUzivatele = static function (int $id): void {
    $historie = $_SESSION['pracovni_uzivatel_predchozi'] ?? [];
    if (! is_array($historie)) {
        $historie = $historie ? [$historie] : [];
    }
    if (($historie[0] ?? null) !== $id) {
        array_unshift($historie, $id);
        $_SESSION['pracovni_uzivatel_predchozi'] = array_slice($historie, 0, 2);
    }
};

if (post('zrusitUzivateleProPraci')) {
    if ($uPracovni) {
        $ulozPredchozihoUzivatele($uPracovni->id());
    }
    Uzivatel::odhlasKlic(Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

// Výběr POSTem z výběru uživatele nebo odkazem s ?pracovni_uzivatel=. Nepřihlášenému se parametr nechá v URL: formulář
// přihlášení se odešle na tutéž adresu a po přihlášení se výběr zpracuje.
$vyberZFormulare = $u !== null && post('vybratUzivateleProPraci');
$vyberZOdkazu = $u !== null && get('pracovni_uzivatel') !== null;
if ($vyberZFormulare || $vyberZOdkazu) {
    $idVybraneho = PracovniUzivatel::parseId($vyberZFormulare ? post('id') : get('pracovni_uzivatel'));
    if (! PracovniUzivatel::isPovolen($u)) {
        chyba('Na práci s vybraným uživatelem nemáš oprávnění', false);
    } elseif ($idVybraneho === null || ! Uzivatel::prihlasId($idVybraneho, Uzivatel::UZIVATEL_PRACOVNI)) {
        chyba('Uživatel pro práci nenalezen', false);
    } elseif ($uPracovni && $uPracovni->id() !== $idVybraneho) {
        $ulozPredchozihoUzivatele($uPracovni->id());
    }
    back($vyberZFormulare
        ? null
        : getCurrentUrlWithQuery([
            'pracovni_uzivatel' => null,
        ]));
}

// Dřív, než formulář zpracuje kterýkoli modul. Nepřihlášenému (odhlásil se v jiné záložce, vypršela mu session)
// formulář nezpracuje žádný modul, a hláška o přepnutém pracovním uživateli by ho jen mátla.
if ($u !== null) {
    FormularPracovnihoUzivatele::hlidej($_POST, $uPracovni);
}

// Nepřihlášený (vypršela mu session, odhlásil se v jiné záložce) se nepřepne, uvidí přihlášení.
if ($u !== null && post('prihlasitSeJakoUzivatel')) {
    // dřív než hledání: kdo přepínat nesmí, se nemá dozvědět, která ID existují
    if (! PrepnutiNaUzivatele::smiPrepinat($u)) {
        chyba(PrepnutiNaUzivatele::NESMI_PREPINAT);
    }
    try {
        $naKoho = Uzivatel::zIdUrcite(post('id'));
        $duvodOdmitnuti = $prepnutiNaUzivatele->getDuvodOdmitnuti($u, $naKoho);
        if ($duvodOdmitnuti !== null) {
            chyba($duvodOdmitnuti);
        }
        $u = $prepnutiNaUzivatele->prepni($u, $naKoho);
        // infopulťák se na vypravěče přepíná, aby za něj vyplnil prezenci
        // null: přepnul se zpět na sebe, ale na jiném zařízení se mezitím odhlásil z ostatních
        back($u !== null && ($u->jeVypravec() || $u->jePartner())
            ? $u->mojeAktivityAdminUrl()
            : null
        );
    } catch (UzivatelNenalezen $uzivatelNenalezen) {
        // ID poslal formulář, hláška se vypisuje jako HTML
        chyba(htmlspecialchars($uzivatelNenalezen->getMessage()));
    }
}
