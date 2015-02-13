<?php

/** 
 * Úvodní karta organizátora s přehledem jeho aktivit
 * 
 * nazev: Moje aktivity
 * pravo: 109
 */

$aktivity = Aktivita::zFiltru(array(
  'organizator' => $u->id(),
  'rok' => ROK,
));

$t = new XTemplate('muj-prehled.xtpl');

if(empty($aktivity)) $t->parse('prehled.zadnaAktivita');
foreach($aktivity as $a) {
  $ucastnici = $a->ucastnici();
  $o = dbQueryS('SELECT id_uzivatele, MAX(cas) as cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele', array($a->id()));
  while($r = mysql_fetch_assoc($o)) {
    $casyPrihlaseni[$r['id_uzivatele']] = new DateTimeCz($r['cas']);
  }
  foreach($ucastnici as $ua) {
    $t->assign(array(
      'jmeno' => $ua->jmenoNick(),
      'mail' => $ua->mail(),
      'telefon' => $ua->telefon(),
      'casPrihlaseni' => $casyPrihlaseni[$ua->id()]->format('j.n. H:i'),
    ));
    $t->parse('prehled.aktivita.ucast.ucastnik');
  }
  if($ucastnici) {
    $t->parse('prehled.aktivita.ucast');
  }
  $t->assign(array(
    'nazevAktivity' => $a->nazev(),
    'obsazenost' => $a->obsazenostHtml(),
    'cas' => $a->denCas(),
    'maily' => implode(';', array_map(function($u){ return $u->mail(); }, $ucastnici)),
    'id' => $a->id(),
  ));
  $t->parse('prehled.aktivita');
}

$t->parse('prehled');
$t->out('prehled');
