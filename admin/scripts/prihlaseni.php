<?php

use Gamecon\Login\Login;
use Gamecon\Exceptions\UzivatelNenalezen;

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
        if ($u->jeSuperAdmin() || ($u->jeInfopultak())) {
            $potencialniUzivatel = Uzivatel::zIdUrcite(post('id'));
            if ($u->jeSuperAdmin() || $potencialniUzivatel->jeVypravec() || $potencialniUzivatel->jePartner()) {
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
