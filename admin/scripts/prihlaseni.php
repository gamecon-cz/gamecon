<?php

/**
 * Kód starající o přihlášení uživatele a výběr uživatele pro práci
 */

// Přihlášení
$u = null;
if(post('loginNAdm') && post('hesloNAdm')) {
  Uzivatel::prihlas(post('loginNAdm'), post('hesloNAdm'));
  back();
}
$u = Uzivatel::zSession();

// Magické přihlášení z administračního rozcestníku na ostré (?gcsso= token).
// Stejná logika jako u novějších ročníků, ale uzpůsobená éře 2014:
//  - get() vrací '' (ne null), takže testujeme na neprázdný řetězec,
//  - Dev třídy žijí v sdilene/Dev/ (tenhle ročník nemá model/),
//  - vendor/ není, autoload taky ne → require_once.
// Pravidla rozhodnutí drží ArchivSsoPrihlaseni; mint je na ostré v
// admin/.../web/stare-rocniky.php. PHP 5.6-kompatibilní soubory.
if (get('gcsso') !== '') {
    $gcsso = get('gcsso');
    require_once __DIR__ . '/../../sdilene/Dev/OvereneSso.php';
    require_once __DIR__ . '/../../sdilene/Dev/CrossSiteLogin.php';
    require_once __DIR__ . '/../../sdilene/Dev/SsoParovaciCookie.php';
    require_once __DIR__ . '/../../sdilene/Dev/ArchivSsoPrihlaseni.php';

    // GAMECON_SSO_KEY = klíč odvozený pro TENTO ročník (HMAC(rok, master)), který
    // archivu vstříkne deploy přes -e. Prázdný → SSO se neuplatní.
    $ssoKlic = defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '';
    $ssoPrihlaseni = new \Gamecon\Dev\ArchivSsoPrihlaseni($ssoKlic);
    $u = $ssoPrihlaseni->prihlas(
        (string) $gcsso,
        \Gamecon\Dev\SsoParovaciCookie::precti(),
        $u
    );

    // Token z URL odstraníme (ať nezůstane v historii / referreru).
    $cistaQuery = $_GET;
    unset($cistaQuery['gcsso']);
    $cilo = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($cistaQuery)) {
        $cilo .= '?' . http_build_query($cistaQuery);
    }
    back($cilo);
}
if(post('odhlasNAdm')) {
  $u->odhlas();
  back();
}

// Výběr uživatele pro práci
$uPracovni = null;
if(post('vybratUzivateleProPraci')) {
  $u = Uzivatel::prihlasId(post('id'), 'uzivatel_pracovni');
  back();
}
$uPracovni = Uzivatel::zSession('uzivatel_pracovni');
if(post('zrusitUzivateleProPraci')) {
  Uzivatel::odhlasKlic('uzivatel_pracovni');
  back();
}
