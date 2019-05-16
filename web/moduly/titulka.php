<?php

$this->bezMenu(true);
$this->bezDekorace(true);

$t->assign([
  'menu'    =>  $menu,
  'blog'    =>  Novinka::zNejnovejsi(Novinka::BLOG) ?? Novinka::vytvorPrazdne(),
  'novinka' =>  Novinka::zNejnovejsi(Novinka::NOVINKA) ?? Novinka::vytvorPrazdne(),
  'a'       =>  $u ? $u->koncA() : '',
]);

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
