<?php

/**
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function nahledPredmetu($soubor) {
  $cesta = 'soubory/obsah/materialy/' . ROK . '/' . $soubor;
  try {
    $nahled = Nahled::zSouboru($cesta)->kvalita(98)->url();
    $nahled .= '.jpg'; // další .jpg na konec kvůli lightbox bugu
  } catch(Exception $e) {
    // pokud soubor neexistuje, nepoužít cache ale vypsat do html přímo cestu
    // při zkoumání html je pak přímo vidět, kam je potřeba nahrát soubor
    $nahled = $cesta;
  }
  return $nahled;
}

if(GC_BEZI || $u && $u->gcPritomen()) {
  echo hlaska('prihlaseniJenInfo');
  return;
}

if(!REG_GC) {
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
  'ubytovani' =>  $shop->ubytovaniHtml(),
  'ulozitNeboPrihlasit' =>  $u->gcPrihlasen() ? 'Uložit změny' : 'Přihlásit na GameCon',
  'vstupne'   =>  $shop->vstupneHtml(),
  'pomoc'     =>  $pomoc->html(),

  // náhledy předmětů
  // záměrně rozkopírované, generování pomocí cyklu bylo neprůhledné
  'tricko'    =>  nahledPredmetu('tricko.jpg'),
  'tricko_m'  =>  nahledPredmetu('tricko_m.jpg'),
  'kostka'    =>  nahledPredmetu('kostka.jpg'),
  'kostka_m'  =>  nahledPredmetu('kostka_m.jpg'),
  'placka'    =>  nahledPredmetu('placka.jpg'),
  'placka_m'  =>  nahledPredmetu('placka_m.jpg'),
]);

$t->parse($u->gcPrihlasen() ? 'prihlaska.prihlasen' : 'prihlaska.neprihlasen');
if($u->gcPrihlasen()) $t->parse('prihlaska.odhlasit');
