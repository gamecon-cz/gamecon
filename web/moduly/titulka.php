<?php

/** @var Modul $this */
$this->blackarrowStyl(true);
$t = $this->sablona();

$typy = serazenePodle(Typ::zViditelnych(), 'poradi');
foreach ($typy as $i => $typ) {
  $t->assign([
    'cislo'   => sprintf('%02d', $i + 1),
    'nazev'   => mb_ucfirst($typ->nazev()),
    'url'     => $typ->url(),
    'obrazek' => 'soubory/systemove/linie/' . $typ->id() . '.jpg',
  ]);
  $t->parse('titulka.linie');
}


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


/*
$this->bezMenu(true);
$this->bezDekorace(true);

$blog = Novinka::zNejnovejsi(Novinka::BLOG);
$novinka = Novinka::zNejnovejsi(Novinka::NOVINKA);
$t->assign([
  'menu'    =>  $menu,
  'novinka' =>  $novinka,
  'a'       =>  $u ? $u->koncA() : '',
]);

if ($blog) {
    $t->assign(['blog' => $blog]);
    $t->parse('titulka.blog');
}

if ($novinka) {
    $t->assign(['novinka' => $novinka]);
    $t->parse('titulka.novinka');
}

if($u && $u->gcPrihlasen() && REG_GC)   $t->parse('titulka.prihlasen');
elseif($u && REG_GC)                    $t->parse('titulka.neprihlasen');
else                                    $t->parse('titulka.info');

if(PROGRAM_VIDITELNY)   $t->parse('titulka.program');
else                    $t->parse('titulka.pripravujeme');

$this->info()
  ->titulek('GameCon – největší festival nepočítačových her')
  ->nazev('GameCon – největší festival nepočítačových her')
  ->popis('GameCon je největší festival nepočítačových her v České republice, který se každoročně koná třetí víkend v červenci. Opět se můžete těšit na desítky RPGček, deskovek, larpů, akčních her, wargaming, přednášky, klání v Příbězích Impéria, tradiční mistrovství v DrD a v neposlední řadě úžasné lidi a vůbec zážitky, které ve vás přetrvají minimálně do dalšího roku.')
  ->url(URL_WEBU);
*/
