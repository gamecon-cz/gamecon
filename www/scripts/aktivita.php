<?php

Aktivita::prihlasovatkoZpracuj($u);
Aktivita::vyberTeamuZpracuj($u);

$xtpl = new XTemplate('./templates/aktivita.xtpl');

$aktivita = current($aktivity);

$xtpl->assign(array(
  'nazev' => $aktivita->nazev(),
  'popis' => $aktivita->popis(),
  'urlObrazku' => $aktivita->obrazek(),
  'titul_orga' => mb_ucfirst($aktivita->orgTitul()),
));

// výpočet a zobrazení ceny
if(CENY_VIDITELNE)
{
  $do = new DateTime(SLEVA_DO);
  $xtpl->assign(array(
    'cena' => $aktivita->cena($u),
    'stdCena' => $aktivita->cena(),
    'zakladniCena' => $aktivita->cenaZaklad().'&thinsp;Kč',
    'rozhodneDatum' => $do->format('j.n.'),
  ));
  if($aktivita->bezSlevy())         $xtpl->parse('aktivita.fixniCena');
  elseif($u && $u->gcPrihlasen())   $xtpl->parse('aktivita.mojeCena');
  else                              $xtpl->parse('aktivita.cena');
}

// zobrazení organizátorů
if(!$aktivita->teamova()) {
  foreach($aktivita->organizatoriSkupiny() as $org) {
    // TODO možná další aktivity organizátora
    // TODO zobrazení orgů stejně bude nutno překopat - nový web
    $xtpl->assign(array(
      'organizator' => $org->jmenoNick(),
    ));
    $xtpl->parse('aktivita.org');
  }
}

// vlastnosti per instance
do {
  $xtpl->assign(array(
    'cas' => $aktivita->denCas(),
    'prihlasovatko' => $aktivita->prihlasovatko($u),
    'org' => $aktivita->teamova() ? '('.$aktivita->orgJmena()[0].')' : '',
    'tridy' => $aktivita->prihlasovatelna() ? '' : 'neprihlasovatelna',
    'obsazenost' => $aktivita->obsazenostHtml(),
  ));
  $xtpl->parse('aktivita.instance');
  if( $v = $aktivita->vyberTeamu($u) ) {
    $xtpl->assign('vyber', $v);
    $xtpl->parse('aktivita.vyberTeamu');
  }
} while($aktivita = next($aktivity));

$xtpl->parse('aktivita');
$xtpl->out('aktivita');
// TODO jak se zjišťuje titulek stránky, když tu není? prověřit, dřív tu assign pro něj extra byl
