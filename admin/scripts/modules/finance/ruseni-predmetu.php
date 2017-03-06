<?php

/**
 * Nástroj na automatické rušení předmětů daného typu uživatelům s zůstatkem
 * menším jak X.
 *
 * nazev: Hromadné rušení objednávek
 * pravo: 108
 */

// nastavení výchozích hodnot
$zustatek = (int)post('zustatek') ?: -20;
$typPredmetu = (int)post('typ') ?: Shop::UBYTOVANI;
$mozneTypy = [
  Shop::JIDLO     =>  'jídlo',
  Shop::UBYTOVANI =>  'ubytování',
  Shop::TRICKO    =>  'tričko',
];
$uzivatele = [];
if(post('vypsat') || post('rusit')) {
  foreach(Uzivatel::zPrihlasenych() as $un) {
    if($un->finance()->stav() < $zustatek) {
      if($un->maPravo(P_NERUSIT_OBJEDNAVKY)) continue;
      $uzivatele[] = $un;
    }
  }
}

// zpracování POST požadavků
if(post('rusit') && $uzivatele) {
  Shop::zrusObjednavkyPro($uzivatele, $typPredmetu);
  oznameni('Objednávky pro ' . count($uzivatele) . ' uživatelů zrušeny.');
}

// vykreslení šablony
$t = new XTemplate(__DIR__ . '/ruseni-predmetu.xtpl');
$t->assign('zustatek', $zustatek);

foreach($mozneTypy as $typId => $typ) {
  $t->assign([
    'id'        =>  $typId,
    'nazev'     =>  $typ,
    'selected'  =>  $typId == $typPredmetu ? 'selected' : '',
  ]);
  $t->parse('ruseniPredmetu.typ');
}

if(post('vypsat') || post('rusit')) {
  $t->parseEach($uzivatele, 'uzivatel', 'ruseniPredmetu.vypis.uzivatel');
  if(!$uzivatele) $t->parse('ruseniPredmetu.vypis.zadniUzivatele');
  $t->parse('ruseniPredmetu.vypis');
}

if(!post('vypsat')) $t->parse('ruseniPredmetu.ruseniBlocker');

$t->parse('ruseniPredmetu');
$t->out('ruseniPredmetu');
