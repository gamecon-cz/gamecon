<?php

use \Gamecon\Cas\DateTimeCz;
/** @var XTemplate $t */

$this->blackarrowStyl(true);
$this->info()
  ->titulek('GameCon – největší festival nepočítačových her')
  ->nazev('GameCon – největší festival nepočítačových her')
  ->popis('GameCon je největší festival nepočítačových her v České republice, který se každoročně koná třetí víkend v červenci. Opět se můžete těšit na desítky RPGček, deskovek, larpů, akčních her, wargaming, přednášky, klání v Příbězích Impéria, tradiční mistrovství v DrD a v neposlední řadě úžasné lidi a vůbec zážitky, které ve vás přetrvají minimálně do dalšího roku.')
  ->url(URL_WEBU);

// linie
$offsety = [120, 320, 280];
$typy = serazenePodle(Typ::zViditelnych(), 'poradi');
foreach ($typy as $i => $typ) {
  $t->assign([
    'cislo'     => sprintf('%02d', $i + 1),
    'nazev'     => mb_ucfirst($typ->nazev()),
    'url'       => $typ->url(),
    'obrazek'   => 'soubory/systemove/linie/' . $typ->id() . '.jpg',
    'ikona'     => 'soubory/systemove/linie-ikony/' . $typ->id() . '.png',
    'aosOffset' => $offsety[$i % 3],
    'popis'     => $typ->popisKratky(),
  ]);
  $t->parse('titulka.linie');
}

// sponzoři a partneři
$obrazky = array_merge(
  glob('soubory/systemove/sponzori/*'),
  glob('soubory/systemove/partneri/*'),
);
foreach ($obrazky as $obrazek) {
  $info = pathinfo($obrazek);
  $t->assign([
    'src' => Nahled::zSouboru($obrazek)->pasuj(120, 60),
    'url' => 'http://' . $info['filename'],
  ]);
  $t->parse('titulka.sponzor');
}

// odpočet
if (pred(REG_GC_OD)) {
  $zacatek = (new DateTimeCz(REG_GC_OD))->format('j. n. \v\e H:i');
  $t->assign([
    'odpocetTimestamp' => strtotime(REG_GC_OD),
    'odpocetNadpis'    => "Přihlašování začne $zacatek",
  ]);
} else {
  $t->assign([
    'odpocetTimestamp' => strtotime(GC_BEZI_OD),
    'odpocetNadpis'    => 'Do GameConu zbývá',
  ]);
  $t->parse('titulka.odpocetPrihlasit');
}

// ostatní
$t->assign([
  'gcOd' => (new DateTimeCz(GC_BEZI_OD))->format('j. n.'),
  'gcDo' => (new DateTimeCz(GC_BEZI_DO))->format('j. n.'),
]);
