<?php

$this->bezMenu(true);
$this->bezDekorace(true);

$t->assign(array(
  'menu'    =>  $menu,
  'blog'    =>  Novinka::zNejnovejsi(Novinka::BLOG),
  'novinka' =>  Novinka::zNejnovejsi(Novinka::NOVINKA),
  'a'       =>  $u ? $u->koncA() : '',
));

if($u && $u->gcPrihlasen() && REG_GC)   $t->parse('titulka.prihlasen');
elseif($u && REG_GC)                    $t->parse('titulka.neprihlasen');
else                                    $t->parse('titulka.info');

if(PROGRAM_VIDITELNY)   $t->parse('titulka.program');
else                    $t->parse('titulka.pripravujeme');
