<?php

declare(strict_types=1);

use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;
use Gamecon\Dev\SsoPrihlaseniZUrl;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Web\CestyKSitemape;
use Gamecon\Web\Info;
use Gamecon\Web\RobotsTxt;
use Gamecon\Web\Sitemap;
use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/../nastaveni/zavadec.php';
require __DIR__ . '/tridy/modul.php';
require __DIR__ . '/tridy/vyjimky.php';

if (HTTPS_ONLY) {
    httpsOnly();
}
omezCsrf();

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

// a file for crawlers may be cached, and a session that the local migration runner has already started
// must not give it its cookie and no-cache headers
$odesliSouborProVyhledavace = static function (string $typObsahu, string $obsah): void {
    if (! headers_sent()) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // saved, not aborted: the runner keeps its record of the endless migrations in the session
            session_write_close();
        }
        header_remove('Set-Cookie');
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: public, max-age=3600');
        header('Content-Type: ' . $typObsahu . '; charset=utf-8');
    }
    echo $obsah;
};

if (($_GET['req'] ?? '') === 'robots.txt') {
    $odesliSouborProVyhledavace('text/plain', (new RobotsTxt($systemoveNastaveni->prostredi(), URL_WEBU . '/sitemap.xml'))->getObsah());

    return;
}

if (($_GET['req'] ?? '') === 'sitemap.xml') {
    // the content is built before any header is sent, so a failing query does not answer with the XML content type
    $odesliSouborProVyhledavace('application/xml', (new Sitemap(URL_WEBU, (new CestyKSitemape())->getCesty()))->getXml());

    return;
}

$u = Uzivatel::zSession();
SsoPrihlaseniZUrl::zpracuj($u);

// Postupná migrace na Symfony: vybrané veřejné cesty obsluhuje Symfony
// controller. Kernel bootujeme JEN pro tyhle cesty (jsou pro nepřihlášené
// uživatele), ať neplatíme režii na každém veřejném requestu. Legacy router
// pracuje s $_GET['req'] (Apache rewrite z .htaccess), takže i tady cestu
// bereme odtud, ne z REQUEST_URI (to nese prefix /web).
$symfonyVerejneCesty = ['/zapomenute-heslo', '/obnova-hesla', '/altcha-challenge'];
$aktualniCesta = '/' . trim((string) ($_GET['req'] ?? ''), '/');
if (in_array($aktualniCesta, $symfonyVerejneCesty, true)) {
    require __DIR__ . '/_symfony.php';
}

try {
    $url = Url::zAktualni();
} catch (UrlException $e) {
    $url = null;
}
// a line or a CMS page is found case-insensitively (/RPG, /rpg), its canonical address is the stored one
$kanonickaCesta = $url?->cela() ?? '';

// určení modulu, který zpracuje požadavek (router)
$m = $url
    ? Modul::zUrl(null, $systemoveNastaveni)
    : Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
// archive of lectures under the page of their line (/prednasky/archiv); a directory web/moduly/prednasky would make
// the sitemap take /prednasky and its pages for a module (CestyKSitemape::isCestaKterouRouterVrati)
if (! $m && $url?->cast(1) === ArchivPrednasekStranka::PODSTRANKA && $url->cast(2) === null
    && ($typArchivu = TypAktivity::zUrl((string) $url->cast(0)))?->id() === TypAktivity::PREDNASKA
) {
    $m = Modul::zNazvu(ArchivPrednasekStranka::MODUL, null, $systemoveNastaveni);
    $m->param('typ', $typArchivu);
    $kanonickaCesta = ArchivPrednasekStranka::cesta($typArchivu);
}
if (! $m && ($stranka = Stranka::zUrl())) {
    if ($stranka->isRedirect()) {
        header('Location: ' . $stranka->redirectUrl(), true, 301);
        exit;
    }
    $m = Modul::zNazvu('stranka', null, $systemoveNastaveni);
    $m->param('stranka', $stranka);
    $kanonickaCesta = $stranka->url();
}
if (! $m && (($typ = TypAktivity::zUrl()) || ($org = Uzivatel::zUrl()))) {
    $m = Modul::zNazvu('aktivity', null, $systemoveNastaveni);
    $m->param('typ', $typ ?: null);
    $m->param('org', ! $typ ? $org : null);
    if ($typ) {
        $kanonickaCesta = $typ->url();
    }
}
if (! $m) {
    $m = Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
}

// spuštění kódu modulu + buffering výstupu a nastavení
$m->param('u', $u);
$m->param('url', $url);
if (($url?->cast(0) ?? null) === 'api') {
    $m->bezStranky(true);
}
$i = (new Info($systemoveNastaveni))
    ->obrazek('soubory/styl/og-image.jpg')
    ->site('GameCon');
if ($url !== null) {
    $i->url(Info::getKanonickaUrlZCesty(URL_WEBU, $kanonickaCesta));
}
$m->info($i);

try {
    $m->spust();
} catch (Nenalezeno $e) {
    $m = Modul::zNazvu('nenalezeno', null, $systemoveNastaveni)->spust();
} catch (Neprihlasen $e) {
    $m = Modul::zNazvu('neprihlasen', null, $systemoveNastaveni)->spust();
}

// sestavení menu
$menu = '';
if (! $m->bezStranky() && ! $m->bezMenu()) {
    $menu = Gamecon\Web\MenuWebu::html($u);
}

// výstup (s ohledem na to co modul nastavil)
if ($m->bezStranky()) {
    echo $m->vystup();

    return;
}

$t = new XTemplate(__DIR__ . '/sablony/blackarrow/index.xtpl');
$t->assign([
    'css'          => perfectcache('soubory/blackarrow/*/*.less'),
    'jsVyjimkovac' => Vyjimkovac::js(URL_WEBU),
    'chyba'        => Chyba::vyzvedniHtml(),
    'menu'         => $menu,
    'obsah'        => $m->vystup(),
    'base'         => URL_WEBU . '/',
    'info'         => $m->info() ? $m->info()->html() : '',
    'letosniRok'   => date('Y'),
]);
$t->parseEach($m->cssUrls(), 'url', 'index.extraCss');
$t->parseEach($m->jsUrls(), 'url', 'index.extraJs');
if (! $m->bezPaticky()) {
    if ($u?->jeOrganizator()) {
        $t->parse('index.paticka.qrka');
    }
    $t->parse('index.paticka');
}

$ribbonLabel = $systemoveNastaveni->prostredi()->ribbonLabel();
if ($ribbonLabel !== '') {
    $t->assign('ribbonLabel', $ribbonLabel);
    $t->parse('index.ribbon');
}
$t->parse('index');
$t->out('index');
profilInfo();
