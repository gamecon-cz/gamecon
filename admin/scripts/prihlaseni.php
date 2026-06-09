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
// Hlavní admin podepsal token na e-mail klikajícího uživatele + nonce a uložil
// stejný nonce do spárovací cookie (.gamecon.cz). Přihlásíme jen když: tady ještě
// nikdo není přihlášený (cizí sezení na archivu nepřepisujeme), token je platný,
// nonce z tokenu sedí s nonce z cookie (= jde o prohlížeč, který klikl — sdílená
// URL nestačí) a uživatele s tím e-mailem máme v téhle DB. Jakékoli selhání je
// tiché: token z URL odstraníme a necháme doběhnout běžné přihlášení.
// Soubory načítáme require_once, ať to funguje i v ročnících bez produkčního
// autoloadu (archivní vendor/ je zmrazené). Pravidla rozhodnutí drží
// ArchivSsoPrihlaseni; mintovací strana je na ostré v admin/.../web/stare-rocniky.php.
if (($gcsso = get('gcsso')) !== null) {
    require_once __DIR__ . '/../../model/Dev/OvereneSso.php';
    require_once __DIR__ . '/../../model/Dev/CrossSiteLogin.php';
    require_once __DIR__ . '/../../model/Dev/SsoParovaciCookie.php';
    require_once __DIR__ . '/../../model/Dev/ArchivSsoPrihlaseni.php';

    // GAMECON_SSO_KEY = klíč odvozený pro TENTO ročník (HMAC(rok, master)), který
    // archivu vstříkne deploy přes -e. NE SECRET_CRYPTO_KEY — ten šifruje osobní data
    // a do (zmrazeného, zranitelného) archivu nepatří. Prázdný → SSO se neuplatní.
    $ssoKlic = defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '';
    $u = (new \Gamecon\Dev\ArchivSsoPrihlaseni($ssoKlic))->prihlas(
        (string) $gcsso,
        \Gamecon\Dev\SsoParovaciCookie::precti(),
        $u,
    );

    // Token z URL odstraníme (ať nezůstane v historii / referreru). Vlastní
    // odstranění místo getCurrentUrlWithQuery — ta ve starších ročnících není.
    $cistaQuery = $_GET;
    unset($cistaQuery['gcsso']);
    $cilo = strtok($_SERVER['REQUEST_URI'], '?');
    if ($cistaQuery !== []) {
        $cilo .= '?' . http_build_query($cistaQuery);
    }
    back($cilo);
}
if(post('odhlasNAdm')) {
  if($u) $u->odhlas();
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
