<?php

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
if (post('loginNAdm') && post('hesloNAdm')) {
    $pravePrihlaseny = Uzivatel::prihlas(post('loginNAdm'), post('hesloNAdm'));
    if (!$pravePrihlaseny) {
        chyba("Chybné přihlašovací jméno nebo heslo");
    }
    back();
}
$u = Uzivatel::zSession();
if (post('odhlasNAdm')) {
    if ($u) {
        $u->odhlas();
    }
    back();
}

// Výběr uživatele pro práci
$uPracovni = null;
if (post('vybratUzivateleProPraci')) {
    $u = Uzivatel::prihlasId(post('id'), 'uzivatel_pracovni');
    back();
}
$uPracovni = Uzivatel::zSession('uzivatel_pracovni');
if (post('zrusitUzivateleProPraci')) {
    Uzivatel::odhlasKlic('uzivatel_pracovni');
    back();
}

if (post('prihlasitSeJakoUzivatel') && $u->isSuperAdmin()) {
    $u = Uzivatel::prihlasId(post('id'));
    back();
}
