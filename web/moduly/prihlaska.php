<?php

if(GAMECON_BEZI || $u && $u->gcPritomen()) {
  echo hlaska('prihlaseniJenInfo');
  return;
}

if(!REGISTRACE_AKTIVNI) {
  echo hlaska('prihlaseniVypnuto');
  return;
}

if(!$u) exit(header('Location: '.URL_WEBU.'/registrace?prihlaska'));

$shop = new Shop($u);
$pomoc = new Pomoc($u);

if(!empty($_POST)) {
  // odhlášení z GameConu
  if(post('odhlasit')) {
    $u->gcOdhlas();
    oznameni(hlaska('odhlaseniZGc',$u));
  }
  // přihlašování nebo editace
  $prihlasovani = false;
  if(!$u->gcPrihlasen())
    $prihlasovani=$u->gcPrihlas();
  $shop->zpracujPredmety();
  $shop->zpracujUbytovani();
  $shop->zpracujSlevy();
  $shop->zpracujJidlo();
  $shop->zpracujVstupne();
  $pomoc->zpracuj();
  if($prihlasovani) {
    $_SESSION['ga_tracking_prihlaska'] = true; //hack pro zobrazení js kódu úspěšné google analytics konverze
    oznameni(hlaska('prihlaseniNaGc',$u));
  } else {
    oznameni(hlaska('aktualizacePrihlasky'));
  }
}

// hack pro zobrazení js kódu úspěšné google analytics konverze
$gaTrack = '';
if(isset($_SESSION['ga_tracking_prihlaska'])) {
  //$gaTrack = "<script>_gaq.push(['_trackEvent', 'gamecon', 'prihlaseni']);</script>"; // GA tracking není funkční
  unset($_SESSION['ga_tracking_prihlaska']);
}

$t->assign([
  'a'         =>  $u->koncA(),
  'gaTrack'   =>  $gaTrack,
  'jidlo'     =>  $shop->jidloHtml(),
  'predmety'  =>  $shop->predmetyHtml(),
  'rok'       =>  ROK,
  'slevy'     =>  $shop->slevyHtml(),
  'ubytovani' =>  $shop->ubytovaniHtml(),
  'ulozitNeboPrihlasit' =>  $u->gcPrihlasen() ? 'Uložit změny' : 'Přihlásit na GameCon',
  'vstupne'   =>  $shop->vstupneHtml(),
  'pomoc'     =>  $pomoc->html(),
]);

$predmety = ['tricko', 'kostka', 'placka'];
foreach($predmety as $p) {
  foreach(['', '_m'] as $k) { // přidaná koncovka (modifikátor) k názvu
    $n = $p.$k;
    $t->assign($n, Nahled::zSouboru('soubory/obsah/materialy/2015/'.$n.'.jpg')->kvalita(98).'.jpg'); //jpg na konec kvůli lightbox bugu
  }
}

$t->parse($u->gcPrihlasen() ? 'prihlaska.prihlasen' : 'prihlaska.neprihlasen');
if($u->gcPrihlasen()) $t->parse('prihlaska.odhlasit');
