<?php

use Gamecon\XTemplate\XTemplate;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Web\Info;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Pravo;

require __DIR__ . '/../nastaveni/zavadec.php';
require __DIR__ . '/tridy/modul.php';
require __DIR__ . '/tridy/vyjimky.php';

if (HTTPS_ONLY) {
    httpsOnly();
}
omezCsrf();

$u = Uzivatel::zSession();

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

try {
    $url = Url::zAktualni();
} catch (UrlException $e) {
    $url = null;
}

// určení modulu, který zpracuje požadavek (router)
$m = $url
    ? Modul::zUrl(null, $systemoveNastaveni)
    : Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
if (!$m && ($stranka = Stranka::zUrl())) {
    $m = Modul::zNazvu('stranka', null, $systemoveNastaveni);
    $m->param('stranka', $stranka);
}
if (!$m && (($typ = TypAktivity::zUrl()) || ($org = Uzivatel::zUrl()))) {
    $m = Modul::zNazvu('aktivity', null, $systemoveNastaveni);
    $m->param('typ', $typ ?: null);
    $m->param('org', !$typ ? $org : null);
}
if (!$m) {
    $m = Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
}

// spuštění kódu modulu + buffering výstupu a nastavení
$m->param('u', $u);
$m->param('url', $url);
    if (($url->cast(0) ?? null) === 'api') {
    $m->bezStranky(true);
}
$i = (new Info($systemoveNastaveni))
    ->obrazek('soubory/styl/og-image.jpg')
    ->site('GameCon')
    ->url("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
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
if (!$m->bezStranky() && !$m->bezMenu()) {
    $t = new XTemplate(__DIR__ . '/sablony/blackarrow/menu.xtpl');

    $typy = serazenePodle(TypAktivity::zViditelnych(), 'poradi');

    // Zkopírujeme původní pole
    $upraveneTypy = $typy;

    foreach ($typy as $typ) { 
        if ($typ->id() === TypAktivity::BONUS) { 
            $typ->nastavNazev('akční hry a bonusy');
        }
    }
    
    $t->parseEach($typy, 'typ', 'menu.typAktivit');

    // položky uživatelského menu
    if ($u) {
        $t->assign(['u' => $u]);
        $t->assign(["gcPrihlaska" => $u->gcPrihlasen() ? "Upravit přihlášku" : "Prihláška na GC"]);
        if ($u->maPravo(Pravo::ADMINISTRACE_INFOPULT) || $u->jeOrganizator()) {
            $t->assign(['uvodniAdminUrl' => $u->uvodniAdminUrl()]);
            $t->parse('menu.prihlasen.admin');
        } else if ($u->maPravo(Pravo::ADMINISTRACE_MOJE_AKTIVITY)) {
            $t->assign(['mojeAktivityAdminUrl' => $u->mojeAktivityAdminUrl()]);
            $t->parse('menu.prihlasen.mujPrehled');
        }

        $t->parse('menu.prihlasen');
    } else {
        $t->parse('menu.neprihlasen');
        $t->assign(["gcPrihlaska" => "Prihláška na GC"]);
    }

    $t->parse('menu');
    $menu = $t->text('menu');
    // TODO odstranit starou třídu menu
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
if (!$m->bezPaticky()) {
    if ($u?->jeOrganizator()){
        $t->assign([
            'odkaz' => 'qrka',
            'nazev' => 'qrka',
        ]);
        $t->parse('index.paticka.qrka');
    }
    $t->parse('index.paticka');
}

if ($systemoveNastaveni->jsmeNaBete()) {
    $t->parse('index.jsmeNaBete');
}
$t->parse('index');
$t->out('index');
profilInfo();
