<?php

require __DIR__ . '/../nastaveni/zavadec.php';
require __DIR__ . '/tridy/modul.php';
require __DIR__ . '/tridy/menu.php';

if(HTTPS_ONLY) httpsOnly();

$u = Uzivatel::zSession();
if ($u) {
  $u->otoc(); // nacti cerstva data do session
}
try {
  $url = Url::zAktualni();
} catch(UrlException $e) {
  $url = null;
}

// určení modulu, který zpracuje požadavek (router)
$m = $url ? Modul::zUrl() : Modul::zNazvu('neexistujici');
if(!$m && ($stranka = Stranka::zUrl())) {
  $m = Modul::zNazvu('stranka');
  $m->param('stranka', $stranka);
}
if(!$m && ( ($typ = Typ::zUrl()) || ($org = Uzivatel::zUrl()) )) {
  $m = Modul::zNazvu('aktivity');
  $m->param('typ', $typ ?: null);
  $m->param('org', !$typ ? $org : null);
}
if(!$m) {
  $m = Modul::zNazvu('neexistujici');
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
} catch(UrlNotFoundException $e) {
  $m = Modul::zNazvu('neexistujici')->spust();
}
if(!$i->titulek())
  if($i->nazev())   $i->titulek($i->nazev().' – GameCon');
  else              $i->titulek('GameCon')->nazev('GameCon');

// výstup (s ohledem na to co modul nastavil)
$menu = '';
if(!$m->bezStranky() && !$m->bezMenu()) {
  $typy = serazenePodle(Typ::zViditelnych(), 'poradi');

  $t = new XTemplate('sablony/blackarrow/menu.xtpl');
  $t->parseEach($typy, 'typ', 'menu.typAktivit');
  $t->parse('menu');
  $menu = $t->text('menu');
  // TODO odstranit staré menu
}

if($m->bezStranky()) {
  echo $m->vystup();
} elseif($m->blackarrowStyl()) {
  $t = new XTemplate('sablony/blackarrow/index.xtpl');
  $t->assign([
    'css'   => perfectcache('soubory/blackarrow/*/*.less'),
    'menu'  => $menu,
    'obsah' => $m->vystup(),
  ]);
  $t->parse('index');
  $t->out('index');
  echo profilInfo();
} else {
  $t = new XTemplate('sablony/index.xtpl');
  // templata a nastavení proměnných do glob templaty
  $t->assign([
    'u'         => $u,
    'base'      => URL_WEBU.'/',
    'admin'     => URL_ADMIN,
    'obsah'     => $m->vystup(),  // TODO nastavování titulku stránky
    'sponzori'  => Modul::zNazvu('sponzori')->spust()->vystup(),
    'css'       => perfectcache(
      'soubory/styl/flaticon.ttf',
      'soubory/styl/easybox.min.css',
      'soubory/styl/styl.less',
      'soubory/styl/fonty.less',
      'soubory/styl/jquery-ui.min.css',
      'soubory/styl/program-skryvani-linii.less',
      'soubory/styl/program-nahled.less',
      'soubory/blackarrow/less/menu.less' // TODO pravděpodobně se změní
    ),
    'js'        => perfectcache(
      'soubory/jquery-2.1.1.min.js',
      'soubory/scroll-sneak.js',
      'soubory/aplikace.js',
      'soubory/jquery-ui.min.js',
      'soubory/easybox.distrib.min.js', //nějaká debiláž, musí být poslední
      'soubory/program-skryvani-linii.js',
      'soubory/program-nahled.js'
    ),
    'jsVyjimkovac'  => Vyjimkovac::js(URL_WEBU.'/ajax-vyjimkovac'),
    'chyba'     => Chyba::vyzvedniHtml(),
    'info'      => $m->info() ? $m->info()->html() : '',
    'a'         => $u ? $u->koncA() : '',
    'datum'     => date('j.', strtotime(GC_BEZI_OD)) . '–' . date('j. n. Y', strtotime(GC_BEZI_DO)),
    'menu'      => $menu,
  ]);
  // tisk věcí a zdar
  if($u && $u->maPravo(P_ADMIN_UVOD))               $t->parse('index.prihlasen.admin');
  elseif($u && $u->maPravo(P_ADMIN_MUJ_PREHLED))    $t->parse('index.prihlasen.mujPrehled');
  if($u && $u->gcPrihlasen() && FINANCE_VIDITELNE)  $t->assign('finance', $u->finance()->stavHr());
  if($u && $u->gcPrihlasen())                       $t->parse('index.prihlasen.gcPrihlasen');
  elseif($u && REG_GC)                              $t->parse('index.prihlasen.gcNeprihlasen');
  if(ANALYTICS)                                     $t->parse('index.analytics');
  $t->parse( $u ? 'index.prihlasen' : 'index.neprihlasen' );
  $t->parse('index');
  $t->out('index');
  echo profilInfo();
}
