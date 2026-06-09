<?php

declare(strict_types=1);

use Gamecon\Dev\ArchivSsoPrihlaseni;
use Gamecon\Dev\SsoParovaciCookie;
use Gamecon\Exceptions\UzivatelNenalezen;
use Gamecon\Login\Login;
use Gamecon\Pravo;

/*
 * Kód starající o přihlášení uživatele a výběr uživatele pro práci
 */
if (! empty($_GET['update_code'])) {
    exec('git pull 2>&1', $output, $returnValue);
    print_r($output);
    exit($returnValue);
}
// Přihlášení
$u = null;
if (post(Login::LOGIN_INPUT_NAME) && post(Login::PASSWORD_INPUT_NAME)) {
    $pravePrihlaseny = Uzivatel::prihlas(post(Login::LOGIN_INPUT_NAME), post(Login::PASSWORD_INPUT_NAME));
    if (! $pravePrihlaseny) {
        chyba('Chybné přihlašovací jméno nebo heslo');
    }
    back();
}
$u = Uzivatel::zSession();

// Magické přihlášení z administračního rozcestníku (?gcsso= token).
// Rozcestník podepsal token na id_uzivatele klikajícího + nonce a uložil stejný
// nonce do spárovací cookie (.gamecon.cz). Přihlásíme jen když: tady ještě nikdo
// není přihlášený (cizí sezení nepřepisujeme), token je platný, nonce z tokenu
// sedí s nonce z cookie (= jde o prohlížeč, který klikl — sdílená URL nestačí) a
// uživatele s tím ID v téhle DB máme. Jakékoli selhání je tiché: token z URL
// odstraníme a necháme doběhnout běžné přihlášení. Pravidla drží ArchivSsoPrihlaseni
// (sdílí je s testem).
//
// Volba ověřovacího klíče podle prostředí:
//   - Archiv ročníku: GAMECON_SSO_KEY = klíč odvozený pro TENTO ročník
//     (HMAC(rok, master)), vstříknutý deployem přes -e. Master tu není.
//   - Preview: GAMECON_SSO_KEY prázdný, ale preview má rovnou master
//     GAMECON_SSO_SECRET (viz deploy-preview-branch.sh) — preview podepisuje i
//     ověřuje sám sebe, takže ověřujeme přímo masterem.
//   - Ostrá: master sice má (kvůli mintování odkazů do archivů/preview), ale
//     NENÍ preview → master k ověření tady nepoužijeme, takže se na ostré SSO
//     login nikdy neuplatní (jsi tu už přihlášený) — což je správně.
// Viz ArchivSsoPrihlaseni + SsoParovaciCookie + admin/scripts/modules/web/{stare-rocniky,previews}.php.
if (($gcsso = get('gcsso')) !== null) {
    $ssoKlic = defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '';
    if ($ssoKlic === '' && jsmeNaPreview() && defined('GAMECON_SSO_SECRET')) {
        $ssoKlic = GAMECON_SSO_SECRET;
    }
    $u = (new ArchivSsoPrihlaseni($ssoKlic))->prihlas(
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
    if (! is_array($historie)) {
        $historie = $historie ? [$historie] : [];
    }
    if (($historie[0] ?? null) !== $id) {
        array_unshift($historie, $id);
        $_SESSION['pracovni_uzivatel_predchozi'] = array_slice($historie, 0, 2);
    }
};

if (post('vybratUzivateleProPraci')) {
    $idAktualniho = $_SESSION[Uzivatel::UZIVATEL_PRACOVNI]['id_uzivatele'] ?? null;
    if ($idAktualniho && $idAktualniho !== (int) post('id')) {
        $ulozPredchozihoUzivatele($idAktualniho);
    }
    $uPracovni = Uzivatel::prihlasId(post('id'), Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if ($idPracovnihoUzivatele = get('pracovni_uzivatel')) {
    if (! $uPracovni || $uPracovni->id() !== $idPracovnihoUzivatele) {
        $idAktualniho = $_SESSION[Uzivatel::UZIVATEL_PRACOVNI]['id_uzivatele'] ?? null;
        if ($idAktualniho && $idAktualniho !== (int) $idPracovnihoUzivatele) {
            $ulozPredchozihoUzivatele($idAktualniho);
        }
        $uPracovni = Uzivatel::prihlasId($idPracovnihoUzivatele, Uzivatel::UZIVATEL_PRACOVNI);
        back(getCurrentUrlWithQuery([
            'pracovni_uzivatel' => null,
        ]));
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
        if ($u->maPravo(Pravo::PREPNUTI_NA_UZIVATELE) || $u->jeInfopultak()) {
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
