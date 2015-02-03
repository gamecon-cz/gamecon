<?php

$naStranku = 15; // bez náhradníků

$t = new XTemplate('prezence-tisk.xtpl');

$aktivity = Aktivita::zIds(get('ids'));

foreach($aktivity as $a) {
  $a->zamci();
  $t->assign('a', $a);
  $i = 0;
  foreach($a->ucastnici() as $uc) {
    $t->assign('u', $uc);
    $t->parse('aktivity.aktivita.ucastnik');
    $i++;
    if($i % $naStranku == 0)  $t->parse('aktivity.aktivita');
  }
  $t->parse('aktivity.aktivita');
}

$t->parse('aktivity');
$t->out('aktivity');
