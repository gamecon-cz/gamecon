<?php

$naStranku = 15;
$prazdnychRadku = 4;
$minNahradniku = 5;

$t = new XTemplate('prezence-tisk.xtpl');

$aktivity = Aktivita::zIds(get('ids'));

// řazení podle typu a názvu
usort($aktivity, function($a, $b) {
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

  // pomocná funkce pro zalamování stránek
  $radkuNaStrance = 0;
  $pridejRadek = function()use(&$radkuNaStrance, $naStranku, $t) {
    $radkuNaStrance++;
    if($radkuNaStrance >= $naStranku) {
      $t->parse('aktivity.aktivita');
      $radkuNaStrance = 0;
    }
  };

  foreach($a->prihlaseni() as $uc) {
    $vek = $uc->vekKDatu($datum);
    if($vek === null) $vek = "?";
    elseif($vek >= 18) $vek = "18+";
    $t->assign('vek', $vek);
    $t->assign('u', $uc);
    $t->parse('aktivity.aktivita.ucastnik');
    $pridejRadek();
  }

  for($i = 0; $i < $prazdnychRadku; $i++) {
    $t->parse('aktivity.aktivita.prazdnyRadek');
    $pridejRadek();
  }

  if($a->nahradnici()) {
    $zbyvaRadku = $naStranku - $radkuNaStrance;
    $potrebaRadku = 2 + min(count($a->nahradnici()), $minNahradniku);
    if($zbyvaRadku < $potrebaRadku) {
      for($i = 0; $i < $zbyvaRadku; $i++) {
        $t->parse('aktivity.aktivita.prazdnyRadek');
      }
      $t->parse('aktivity.aktivita');
      $radkuNaStrance = 0;
    }

    $t->parse('aktivity.aktivita.hlavickaNahradnik');
    $radkuNaStrance += 2; // hlavička zabírá 2 řádky

    foreach($a->nahradnici() as $nahradnik) {
      $vek = $nahradnik->vekKDatu($datum);
      if($vek === null) $vek = "?";
      elseif($vek >= 18) $vek = "18+";
      $t->assign('vek', $vek);
      $t->assign('u', $nahradnik);
      $t->parse('aktivity.aktivita.nahradnik');
      $pridejRadek();

      // náhradníky tisknout jen do konce stránky
      if($radkuNaStrance == 0) break;
    }
  }

  // dotisknout zbytek stránky, pokud je třeba
  while($radkuNaStrance > 0) {
    $t->parse('aktivity.aktivita.prazdnyRadek');
    $pridejRadek();
  }
}

$t->parse('aktivity');
$t->out('aktivity');
