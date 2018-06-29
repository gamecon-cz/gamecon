<?php

$naStranku = 15; // bez náhradníků

$t = new XTemplate('prezence-tisk.xtpl');

$aktivity = Aktivita::zIds(get('ids'));

//Řazení podle typu a názvu.
$aktivity->uasort(function ($a, $b) {
  $c = $a->typId() - $b->typId(); // seřazní podle typu aktivity
  if($c != 0) {
    return $c;
  }

  return strcmp($a->nazev(), $b->nazev()); // seřazení podle názvu aktivity
});

foreach($aktivity as $a) {
  $datum = $a->zacatek();
  $a->zamci();
  $t->assign('a', $a);
  $i = 0; // počet účastníků na stránce
  $j = 0; // zpracovaný počet účastníků

  $pocetUcastniku = count($a->prihlaseni());
  $pocetNahradniku = count($a->nahradnici());

  foreach($a->prihlaseni() as $uc) {
    $vek = $uc->vekKDatu($datum);
    if($vek === null) $vek = "?";
    elseif($vek >= 18) $vek = "18+";
    $t->assign('vek', $vek);
    $t->assign('u', $uc);
    $t->parse('aktivity.aktivita.ucastnik');
    $i++;
    $j++;
    if($i % $naStranku == 0) {
      if($j == $pocetUcastniku) {
        $t->parse('aktivity.aktivita.volneRadky');
      }
      $i = 0;
      $t->parse('aktivity.aktivita');
    }
  }

  if($i != 0) {
    $t->parse('aktivity.aktivita.volneRadky');
  }
  
  if($i > ($naStranku - 5) && $pocetNahradniku > 0) { //náhradníci na novou stranu, pokud se jich nevejde alespoň 5
    $t->parse('aktivity.aktivita');
    $i = 0;
  }

  if ($pocetNahradniku > 0) {
    $t->parse('aktivity.aktivita.hlavickaNahradnik');
  }

  foreach($a->nahradnici() as $un) {
    $vek = $un->vekKDatu($datum);
    if($vek === null) $vek = "?";
    elseif($vek >= 18) $vek = "18+";
    $t->assign('vek', $vek);
    $t->assign('u', $un);
    $t->parse('aktivity.aktivita.nahradnik');
    $i++;
    if($i % $naStranku == 0) {
      break;
    }
  }
  $t->parse('aktivity.aktivita');
}

$t->parse('aktivity');
$t->out('aktivity');
