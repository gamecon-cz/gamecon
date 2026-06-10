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
  Uzivatel::prihlas(post('loginNAdm'), post('hesloNAdm'));
  back();
}
$u = Uzivatel::zSession();

// Magické přihlášení z administračního rozcestníku na ostré (?gcsso= token).
// Hlavní admin podepsal token na id_uzivatele klikajícího + nonce a uložil stejný
// nonce do spárovací cookie (.gamecon.cz). Přihlásíme jen když: tady ještě nikdo
// není přihlášený (cizí sezení na archivu nepřepisujeme), token je platný, nonce
// z tokenu sedí s nonce z cookie (= jde o prohlížeč, který klikl — sdílená URL
// nestačí) a uživatele s tím ID máme v téhle DB. Jakékoli selhání je tiché: token
// z URL odstraníme a necháme doběhnout běžné přihlášení. Pravidla drží
// ArchivSsoPrihlaseni; mintovací strana je na ostré v admin/.../web/stare-rocniky.php.
// PHP 5.6-kompatibilní (tenhle ročník běží na starším PHP) — require_once místo
// autoloadu, žádné str_contains / trailing comma / typehinty.
if (get('gcsso') !== null) {
    $gcsso = get('gcsso');
    require_once __DIR__ . '/../../model/Dev/OvereneSso.php';
    require_once __DIR__ . '/../../model/Dev/CrossSiteLogin.php';
    require_once __DIR__ . '/../../model/Dev/SsoParovaciCookie.php';
    require_once __DIR__ . '/../../model/Dev/ArchivSsoPrihlaseni.php';

    // GAMECON_SSO_KEY = klíč odvozený pro TENTO ročník (HMAC(rok, master)), který
    // archivu vstříkne deploy přes -e. NE SECRET_CRYPTO_KEY. Prázdný → SSO se neuplatní.
    $ssoKlic = defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '';
    $ssoPrihlaseni = new \Gamecon\Dev\ArchivSsoPrihlaseni($ssoKlic);
    $u = $ssoPrihlaseni->prihlas(
        (string) $gcsso,
        \Gamecon\Dev\SsoParovaciCookie::precti(),
        $u
    );

    // Token z URL odstraníme (ať nezůstane v historii / referreru). Vlastní
    // odstranění místo getCurrentUrlWithQuery — ta ve starších ročnících není.
    $cistaQuery = $_GET;
    unset($cistaQuery['gcsso']);
    $cilo = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($cistaQuery)) {
        $cilo .= '?' . http_build_query($cistaQuery);
    }
    back($cilo);
}
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
