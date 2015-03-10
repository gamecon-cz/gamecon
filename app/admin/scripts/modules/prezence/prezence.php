<?php

/** 
 * Vyklikávací tabulky s prezencí na aktivity
 *
 * nazev: Prezence
 * pravo: 103
 */

if(post('prezenceAktivity')) {
  $a = Aktivita::zId(post('prezenceAktivity'));
  $dorazili = Uzivatel::zIds(array_keys(post('dorazil') ?: array()));
  $a->ulozPrezenci($dorazili);
  back();
}

$t = new XTemplate('prezence.xtpl');

require('_casy.php'); // vhackování vybírátka času
$t->assign('casy', _casy($zacatek));

$aktivity = $zacatek ? Aktivita::zRozmezi($zacatek, $zacatek) : array();

if($zacatek && !$aktivity->valid()) $t->parse('prezence.zadnaAktivita');
if(!$zacatek) $t->parse('prezence.nevybrano');

foreach($aktivity as $a) {
  $vyplnena = $a->vyplnenaPrezence();
  $zamcena = $a->zamcena();
  $t->assign('a', $a);
  foreach($a->prihlaseni() as $uc) {
    $t->assign('u', $uc);
    if(!$vyplnena && $zamcena) $t->parse('prezence.aktivita.form.ucastnik.checkbox');
    $t->parse('prezence.aktivita.form.ucastnik.' . ($uc->gcPritomen() ? 'pritomen' : 'nepritomen'));
    $t->parse('prezence.aktivita.form.ucastnik');
  }
  if($vyplnena) $t->parse('prezence.aktivita.vyplnena');
  if(!$vyplnena && $zamcena) $t->parse('prezence.aktivita.form.submit');
  if(!$zamcena) $t->parse('prezence.aktivita.nezamknuta');
  $t->parse('prezence.aktivita.form');
  $t->parse('prezence.aktivita');
}

$t->parse('prezence');
$t->out('prezence');
