<?php

/**
 * Úvodní karta organizátora s přehledem jeho aktivit
 *
 * nazev: Moje aktivity
 * pravo: 109
 */

use \Gamecon\Cas\DateTimeCz;

$aktivity = Aktivita::zFiltru([
  'organizator' => $u->id(),
  'rok' => ROK,
]);

$t = new XTemplate('muj-prehled.xtpl');

if(empty($aktivity)) $t->parse('prehled.zadnaAktivita');
foreach($aktivity as $a) {
  $ucastnici = $a->prihlaseni();
  $o = dbQueryS('SELECT id_uzivatele, MAX(cas) as cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele', [$a->id()]);
  while($r = mysqli_fetch_assoc($o)) {
    $casyPrihlaseni[$r['id_uzivatele']] = new DateTimeCz($r['cas']);
  }
  foreach($ucastnici as $ua) {
    $vek = $ua->vekKDatu($a->zacatek());
    if($vek === null) $vek = "?";
    elseif($vek >= 18) $vek = "18+";
    $t->assign([
      'jmeno'         => $ua->jmenoNick(),
      'mail'          => $ua->mail(),
      'vek'           => $vek,
      'telefon'       => $ua->telefon(),
      'casPrihlaseni' => isset($casyPrihlaseni[$ua->id()]) ? $casyPrihlaseni[$ua->id()]->format('j.n. H:i') : '<i>???</i>',
    ]);
    $t->parse('prehled.aktivita.ucast.ucastnik');
  }
  if($ucastnici) {
    $t->parse('prehled.aktivita.ucast');
  }
  $t->assign([
    'nazevAktivity' => $a->nazev(),
    'obsazenost' => $a->obsazenostHtml(),
    'cas' => $a->denCas(),
    'maily' => implode(';', array_map(function($u){ return $u->mail(); }, $ucastnici)),
    'id' => $a->id(),
  ]);
  $t->parse('prehled.aktivita');
}

$t->assign('manual', Stranka::zUrl('manual-vypravece')->html());
$t->parse('prehled');
$t->out('prehled');
