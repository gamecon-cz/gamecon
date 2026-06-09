<?php

use Gamecon\Login\Login;

$coze = dbConnect()->query('select * from r_zidle_soupis where id_zidle = 1')->fetch_all();

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
if (post('odhlasNAdm')) {
    if ($u) {
        $u->odhlas();
    }
    back();
}

// Výběr uživatele pro práci
$uPracovni = null;
if (post('vybratUzivateleProPraci')) {
    $uPracovni = Uzivatel::prihlasId(post('id'), Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if ($idPracovnihoUzivatele = get('pracovni_uzivatel')) {
    if (!$uPracovni || $uPracovni->id() != $idPracovnihoUzivatele) {
        $uPracovni = Uzivatel::prihlasId($idPracovnihoUzivatele, Uzivatel::UZIVATEL_PRACOVNI);
        back(getCurrentUrlWithQuery(['pracovni_uzivatel' => null]));
    }
}

$uPracovni = Uzivatel::zSession(Uzivatel::UZIVATEL_PRACOVNI);
if (post('zrusitUzivateleProPraci')) {
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
    } catch (\Gamecon\Exceptions\UzivatelNenalezen $uzivatelNenalezen) {
        chyba($uzivatelNenalezen->getMessage());
    }
}
