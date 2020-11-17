<?php

/** @var Modul $this */
$this->blackarrowStyl(true);
$t = $this->sablona();
$t->parseEach([
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_boj.png',
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_wrg.png',
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_warg.png',
  'soubory/blackarrow/zazijes/test_boj.png',
  'soubory/blackarrow/zazijes/test_boj.png',
  'soubory/blackarrow/zazijes/test_boj.png',
  'soubory/blackarrow/zazijes/test_warg.png',
], 'linie', 'titulka.linie');

$t->parseEach([ // TODO uložení sponzorů dořešit
  'soubory/blackarrow/sponzori/sponzori/altar.cz.gif',
  'soubory/blackarrow/sponzori/sponzori/blackfire.cz.jpg',
  'soubory/blackarrow/sponzori/sponzori/blackoil.cz.jpg',
  'soubory/blackarrow/sponzori/sponzori/Fafrin.jpg',
  'soubory/blackarrow/sponzori/sponzori/fantasyobchod.cz.jpg',
  'soubory/blackarrow/sponzori/sponzori/Fist.jpg',
  'soubory/blackarrow/sponzori/sponzori/hopestudio.cz.jpg',
  'soubory/blackarrow/sponzori/sponzori/www.pardubice.eu.jpg',
  'soubory/blackarrow/sponzori/sponzori/albi.cz.png',
  'soubory/blackarrow/sponzori/sponzori/czechgames.com.png',
  'soubory/blackarrow/sponzori/sponzori/mindok.cz.png',
  'soubory/blackarrow/sponzori/sponzori/pardubice.eu.png',
  'soubory/blackarrow/sponzori/sponzori/pardubickykraj.cz.png',
  'soubory/blackarrow/sponzori/sponzori/rexhry.cz.png',
  'soubory/blackarrow/sponzori/sponzori/www.dmpce.cz.png',
  'soubory/blackarrow/sponzori/sponzori/www.tlamagames.com.png',
], 'src', 'titulka.sponzor');


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
