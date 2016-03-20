<?php

/** 
 * Stránka pro přehled všech přihlášených na aktivitu DrD. DrD je starý modul a 
 * přes všechnu snahu je na pozadí black magic! 
 *
 * nazev: Seznam na DrD
 * pravo: 102
 */

if(post('vypadliSemifinale') || post('vypadliFinale')) {
  $a = Aktivita::zId(post('zakladni'));
  $aVypadli = post('vypadliSemifinale') ? Aktivita::zId(post('semifinale')) : Aktivita::zId(post('finale'));
  foreach($a->prihlaseni() as $uc) {
    $aVypadli->odhlas($uc, Aktivita::BEZ_POKUT);
  }
  back();
}

$t = new XTemplate(__DIR__ . '/prihlaseni-drd.xtpl');

$semifinale = [];
$finale = [];
foreach(Aktivita::zFiltru(['typ' => 9, 'rok' => ROK]) as $a) {
  if($a->cenaZaklad() == 0) {
    assert(stripos($a->nazev(), 'finále')); // (semi)finále nebo finále musí být v názvu
    continue; // hack na určení finále a semifinále
  }
  if(!$finale) { // načtení finále a semifinále
    foreach($a->deti() as $dite) $semifinale[] = $dite;
    foreach($semifinale[0]->deti() as $dite) $finale[] = $dite;
  }
  // tisk konkrétní družiny / aktivity
  $t->assign('a', $a);
  $uc = null;
  foreach($a->prihlaseni() as $uc) {
    $t->assign('u', $uc);
    $t->parse('drd.druzina.clen');
  }
  if($uc === null) continue; //jarik je debil a má družiny o 0 lidech, fixme
  if($uc && !$semifinale[0]->prihlasen($uc) && !$semifinale[1]->prihlasen($uc)) {
    $t->parse('drd.druzina.zakladni');
  } elseif($uc && !$finale[0]->prihlasen($uc)) {
    $t->parse('drd.druzina.semifinale');
  } elseif($finale[0]->zamcena()) {
    $t->parse('drd.druzina.finale');
  } elseif(!$a->zamcena()) {
    $t->parse('drd.druzina.nezamceno');
  } else {
    if($semifinale[0]->prihlasen($uc)) $t->assign('sfid', $semifinale[0]->id());
    if($semifinale[1]->prihlasen($uc)) $t->assign('sfid', $semifinale[1]->id());
    if($finale[0]->prihlasen($uc)) $t->assign('fid', $finale[0]->id());
    $t->parse('drd.druzina.vyber');
  }
  $t->parse('drd.druzina');
}

$t->parse('drd');
$t->out('drd');
