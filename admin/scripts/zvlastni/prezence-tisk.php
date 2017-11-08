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
  $a->zamci();
  $t->assign('a', $a);
  $i = 0;
  foreach($a->prihlaseni() as $uc) {
    $t->assign('u', $uc);
    $t->parse('aktivity.aktivita.ucastnik');
    $i++;
    if($i % $naStranku == 0)  $t->parse('aktivity.aktivita');
  }
  $t->parse('aktivity.aktivita');
}

$t->parse('aktivity');
$t->out('aktivity');
