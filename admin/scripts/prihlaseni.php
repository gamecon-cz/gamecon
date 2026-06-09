<?php

use Gamecon\Dev\ArchivSsoPrihlaseni;
use Gamecon\Dev\SsoParovaciCookie;
use Gamecon\Login\Login;
use Gamecon\Exceptions\UzivatelNenalezen;
use Gamecon\Pravo;

/**
 * Kód starající o přihlášení uživatele a výběr uživatele pro práci
 */
if (!empty($_GET['update_code'])) {
    exec('git pull 2>&1', $output, $returnValue);
    print_r($output);
    exit($returnValue);
}
// Přihlášení
$u = null;
if (post(Login::LOGIN_INPUT_NAME) && post(Login::PASSWORD_INPUT_NAME)) {
    $pravePrihlaseny = Uzivatel::prihlas(post(Login::LOGIN_INPUT_NAME), post(Login::PASSWORD_INPUT_NAME));
    if (!$pravePrihlaseny) {
        chyba("Chybné přihlašovací jméno nebo heslo");
    }
    back();
}
$u = Uzivatel::zSession();

// Magické přihlášení z administračního rozcestníku na ostré (?gcsso= token).
// Hlavní admin podepsal token na e-mail klikajícího uživatele + nonce a uložil
// stejný nonce do spárovací cookie (.gamecon.cz). Přihlásíme jen když: tady ještě
// nikdo není přihlášený (cizí sezení na archivu nepřepisujeme), token je platný,
// nonce z tokenu sedí s nonce z cookie (= jde o prohlížeč, který klikl — sdílená
// URL nestačí) a uživatele s tím e-mailem máme v téhle DB. Jakékoli selhání je
// tiché: token z URL odstraníme a necháme doběhnout běžné přihlášení. Pravidla
// rozhodnutí drží ArchivSsoPrihlaseni (sdílí je s testem).
// Viz ArchivSsoPrihlaseni + SsoParovaciCookie + admin/scripts/modules/web/stare-rocniky.php.
if (($gcsso = get('gcsso')) !== null) {
    $u = (new ArchivSsoPrihlaseni(SECRET_CRYPTO_KEY))->prihlas(
        (string) $gcsso,
        SsoParovaciCookie::precti(),
        $u,
    );
    back(getCurrentUrlWithQuery([
        'gcsso' => null,
    ]));
}

if (post('odhlasNAdm')) {
    if ($u) {
        $u->odhlas(false);
    }
    back();
}

// Výběr uživatele pro práci
$uPracovni = null;

$ulozPredchozihoUzivatele = function (int $id) {
    $historie = $_SESSION['pracovni_uzivatel_predchozi'] ?? [];
    if (!is_array($historie)) {
        $historie = $historie ? [$historie] : [];
    }
    if (($historie[0] ?? null) !== $id) {
        array_unshift($historie, $id);
        $_SESSION['pracovni_uzivatel_predchozi'] = array_slice($historie, 0, 2);
    }
};

if (post('vybratUzivateleProPraci')) {
    $idAktualniho = $_SESSION[Uzivatel::UZIVATEL_PRACOVNI]['id_uzivatele'] ?? null;
    if ($idAktualniho && $idAktualniho != (int)post('id')) {
        $ulozPredchozihoUzivatele($idAktualniho);
    }
    $uPracovni = Uzivatel::prihlasId(post('id'), Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if ($idPracovnihoUzivatele = get('pracovni_uzivatel')) {
    if (!$uPracovni || $uPracovni->id() != $idPracovnihoUzivatele) {
        $idAktualniho = $_SESSION[Uzivatel::UZIVATEL_PRACOVNI]['id_uzivatele'] ?? null;
        if ($idAktualniho && $idAktualniho != (int)$idPracovnihoUzivatele) {
            $ulozPredchozihoUzivatele($idAktualniho);
        }
        $uPracovni = Uzivatel::prihlasId($idPracovnihoUzivatele, Uzivatel::UZIVATEL_PRACOVNI);
        back(getCurrentUrlWithQuery(['pracovni_uzivatel' => null]));
    }
}

$uPracovni = Uzivatel::zSession(Uzivatel::UZIVATEL_PRACOVNI);
if (post('zrusitUzivateleProPraci')) {
    if ($uPracovni) {
        $ulozPredchozihoUzivatele($uPracovni->id());
    }
    Uzivatel::odhlasKlic(Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if (post('prihlasitSeJakoUzivatel')) {
    try {
        if ($u->maPravo(Pravo::PREPNUTI_NA_UZIVATELE) || ($u->jeInfopultak())) {
            $potencialniUzivatel = Uzivatel::zIdUrcite(post('id'));
            if ($u->maPravo(Pravo::PREPNUTI_NA_UZIVATELE) || $potencialniUzivatel->jeVypravec() || $potencialniUzivatel->jePartner()) {
                $u = Uzivatel::prihlasId(post('id'));
                back($u->jeVypravec() || $u->jePartner()
                    ? $u->mojeAktivityAdminUrl()
                    : null
                );
            }
        }
    } catch (UzivatelNenalezen $uzivatelNenalezen) {
        chyba($uzivatelNenalezen->getMessage());
    }
}
