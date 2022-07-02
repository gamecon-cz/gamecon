<?php

use Gamecon\Aktivita\TypAktivity;

require __DIR__ . '/../nastaveni/zavadec.php';
require __DIR__ . '/tridy/modul.php';
require __DIR__ . '/tridy/menu.php';
require __DIR__ . '/tridy/vyjimky.php';

if (HTTPS_ONLY) {
    httpsOnly();
}
omezCsrf();

$u = Uzivatel::zSession();

try {
    $url = Url::zAktualni();
} catch (UrlException $e) {
    $url = null;
}

// určení modulu, který zpracuje požadavek (router)
$m = $url ? Modul::zUrl() : Modul::zNazvu('nenalezeno');
if (!$m && ($stranka = Stranka::zUrl())) {
    $m = Modul::zNazvu('stranka');
    $m->param('stranka', $stranka);
}
if (!$m && (($typ = TypAktivity::zUrl()) || ($org = Uzivatel::zUrl()))) {
    $m = Modul::zNazvu('aktivity');
    $m->param('typ', $typ ?: null);
    $m->param('org', !$typ ? $org : null);
}
if (!$m) {
    $m = Modul::zNazvu('nenalezeno');
}

// spuštění kódu modulu + buffering výstupu a nastavení
$m->param('u', $u);
$m->param('url', $url);
$i = (new Info())
    ->obrazek('soubory/styl/og-image.jpg')
    ->site('GameCon')
    ->url("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
$m->info($i);

try {
    $m->spust();
} catch (Nenalezeno $e) {
    $m = Modul::zNazvu('nenalezeno')->spust();
} catch (Neprihlasen $e) {
    $m = Modul::zNazvu('neprihlasen')->spust();
}
if (!$i->titulek()) {
    if ($i->nazev()) {
        $i->titulek($i->nazev() . ' – GameCon');
    } else {
        $i->titulek('GameCon')->nazev('GameCon');
    }
}

// sestavení menu
$menu = '';
if (!$m->bezStranky() && !$m->bezMenu()) {
    $t = new XTemplate('sablony/blackarrow/menu.xtpl');

    $typy = serazenePodle(TypAktivity::zViditelnych(), 'poradi');
    $t->parseEach($typy, 'typ', 'menu.typAktivit');

    // položky uživatelského menu
    if ($u) {
        $t->assign([
            'u' => $u,
            'uvodniAdminUrl' => $u->uvodniAdminUrl(URL_ADMIN),
            'mojeAktivityAdminUrl' => $u->mojeAktivityAdminUrl(URL_ADMIN),
        ]);

        if ($u->maPravo(P_ADMIN_UVOD)) {
            $t->parse('menu.prihlasen.admin');
        } elseif ($u->maPravo(P_ADMIN_MUJ_PREHLED)) {
            $t->parse('menu.prihlasen.mujPrehled');
        }

        $t->parse('menu.prihlasen');
    } else {
        $t->parse('menu.neprihlasen');
    }

    $t->parse('menu');
    $menu = $t->text('menu');
    // TODO odstranit starou třídu menu
}

// výstup (s ohledem na to co modul nastavil)
if ($m->bezStranky()) {
    echo $m->vystup();
} elseif ($m->blackarrowStyl()) {
    $t = new XTemplate('sablony/blackarrow/index.xtpl');
    $t->assign([
        'css' => perfectcache('soubory/blackarrow/*/*.less'),
        'jsVyjimkovac' => \Gamecon\Vyjimkovac\Vyjimkovac::js(URL_WEBU),
        'chyba' => Chyba::vyzvedniHtml(),
        'menu' => $menu,
        'obsah' => $m->vystup(),
        'base' => URL_WEBU . '/',
        'info' => $m->info()->html(),
        'letosniRok' => date('Y'),
    ]);
    $t->parseEach($m->cssUrls(), 'url', 'index.extraCss');
    $t->parseEach($m->jsUrls(), 'url', 'index.extraJs');
    if (!$m->bezPaticky()) {
        $t->parse('index.paticka');
    }
    $t->parse('index');
    $t->out('index');
    echo profilInfo();
} else {
    $t = new XTemplate('sablony/index.xtpl');
    // templata a nastavení proměnných do glob templaty
    $t->assign([
        'u' => $u,
        'base' => URL_WEBU . '/',
        'admin' => URL_ADMIN,
        'obsah' => $m->vystup(),  // TODO nastavování titulku stránky
        'sponzori' => Modul::zNazvu('sponzori')->spust()->vystup(),
        'css' => perfectcache(
            'soubory/styl/flaticon.ttf',
            'soubory/styl/easybox.min.css',
            'soubory/styl/styl.less',
            'soubory/styl/fonty.less',
            'soubory/styl/jquery-ui.min.css',
            'soubory/blackarrow/menu/menu.less'
        ),
        'js' => perfectcache(
            'soubory/jquery-2.1.1.min.js',
            'soubory/aplikace.js',
            'soubory/jquery-ui.min.js',
            'soubory/easybox.distrib.min.js' // nějaká debiláž, musí být poslední
        ),
        'jsVyjimkovac' => \Gamecon\Vyjimkovac\Vyjimkovac::js(URL_WEBU),
        'chyba' => Chyba::vyzvedniHtml(),
        'info' => $m->info() ? $m->info()->html() : '',
        'a' => $u ? $u->koncA() : '',
        'datum' => date('j.', strtotime(GC_BEZI_OD)) . '–' . date('j. n. Y', strtotime(GC_BEZI_DO)),
        'menu' => $menu,
    ]);
    // tisk věcí a zdar
    if ($u && $u->maPravo(P_ADMIN_UVOD)) $t->parse('index.prihlasen.admin');
    elseif ($u && $u->maPravo(P_ADMIN_MUJ_PREHLED)) $t->parse('index.prihlasen.mujPrehled');
    if ($u && $u->gcPrihlasen() && FINANCE_VIDITELNE) $t->assign('finance', $u->finance()->stavHr());
    if ($u && $u->gcPrihlasen()) $t->parse('index.prihlasen.gcPrihlasen');
    elseif ($u && REG_GC) $t->parse('index.prihlasen.gcNeprihlasen');
    if (ANALYTICS) $t->parse('index.analytics');
    $t->parse($u ? 'index.prihlasen' : 'index.neprihlasen');
    $t->parse('index');
    $t->out('index');
    echo profilInfo();
}
