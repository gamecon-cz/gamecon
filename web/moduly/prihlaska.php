<?php

$this->blackarrowStyl(true);
$this->bezPaticky(true);

/**
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 * TODO spíš vyhodit? - možná ručně přidat ?v k souboru
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

if(!$u) exit(header('Location: '.URL_WEBU.'/registrace'));

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

// informace o slevách (jídlo nevypisovat, protože tabulka správně vypisuje cenu po slevě)
$slevy = $u->finance()->slevyVse();
$slevy = array_diff($slevy, ['jídlo zdarma', 'jídlo se slevou']);
if ($slevy) {
  $t->assign([
    'slevy' => implode(', ', $slevy),
    'titul' => mb_strtolower($u->status()),
  ]);
  $t->parse('prihlaska.slevy');
}

$t->assign('ka', $u->koncA() ? 'ka' : '');
if ($u->maPravo(P_UBYTOVANI_ZDARMA)) {
  $t->parse('prihlaska.ubytovaniInfoOrg');
} else if ($u->maPravo(P_ORG_AKCI) && !$u->maPravo(P_NEMA_SLEVU_AKTIVITY)) {
  $t->parse('prihlaska.ubytovaniInfoVypravec');
}

// náhledy
$nahledy = [
  ['tricko.jpg',   'tricko_m.jpg',   'Tričko'],
  ['kostka.jpg',   'kostka_m.jpg',   'Kostka'],
  ['placka.jpg',   'placka_m.jpg',   'Placka'],
  ['nicknack.jpg', 'nicknack_m.jpg', 'Nicknack'],
  ['batoh.jpg',    'batoh_m.jpg',    'Batoh'],
];
foreach($nahledy as $nahled) {
  $t->assign([
    'obrazek'   => nahledPredmetu($nahled[0]),
    'miniatura' => nahledPredmetu($nahled[1]),
    'nazev'     => $nahled[2],
  ]);
  $t->parse('prihlaska.nahled');
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
]);

$t->parse($u->gcPrihlasen() ? 'prihlaska.prihlasen' : 'prihlaska.neprihlasen');
if($u->gcPrihlasen()) $t->parse('prihlaska.odhlasit');
