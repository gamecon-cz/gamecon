<?php

//TODO přesměrování na kanonickou URL pokud je podle GET vybrán např. jen vypravěč

Aktivita::prihlasovatkoZpracuj($u);
Aktivita::vyberTeamuZpracuj($u);

$a = current($aktivity);
$dalsi = next($aktivity);
while($a) {

  // vlastnosti per termín
  $t->assign(array(
    'a'             =>  $a,
    'prihlasovatko' =>  $a->prihlasovatko($u),
    //TODO ne/přihlašovatelnost odlišená vzhledem (třídou?) termínu aktivity
    //TODO ajax na zobrazení bubliny ne/úspěšného přihlášení
  ));
  if( $v = $a->vyberTeamu($u) ) {
    $xtpl->assign('vyber', $v);
    $xtpl->parse('aktivity.aktivita.vyberTeamu');
  }
  $t->parse('aktivity.aktivita.termin');

  // vlastnosti per skupina (hack)
  if(!$dalsi || !$dalsi->patriPod() || $dalsi->patriPod() != $a->patriPod()) {
    if(CENY_VIDITELNE) {
      $do = new DateTime(SLEVA_DO);
      $t->assign(array(
        'cena' => $a->cena($u),
        'stdCena' => $a->cena(),
        'zakladniCena' => $a->cenaZaklad().'&thinsp;Kč',
        'rozhodneDatum' => $do->format('j.n.'),
        //TODO způsob načtení a zobrazení orgů (per termín, per aktivita, proklik na jejich osobní stránku, ...?)
        //TODO optimalizace načítání popisků (do jiné tabulky, jeden dotaz, pokud bude výkonnostně problém)
      ));
      if($a->bezSlevy())                $t->parse('aktivity.aktivita.fixniCena');
      elseif($u && $u->gcPrihlasen())   $t->parse('aktivity.aktivita.mojeCena');
      else                              $t->parse('aktivity.aktivita.cena');
    }
    foreach($a->tagy() as $tag) {
      $t->assign('tag', $tag);
      $t->parse('aktivity.aktivita.tag');
    }
    $t->parse('aktivity.aktivita');
  }

  // bižuterie pro běh cyklu
  $a = $dalsi;
  $dalsi = next($aktivity);

}

// záhlaví
if(isset($org)) {
  $t->assign('text', $org->jmenoNick());
  $t->parse('aktivity.zahlavi');
}

if(isset($typ)) {
  $t->assign('text', $typ->oTypu());
  $t->parse('aktivity.zahlavi');
}
