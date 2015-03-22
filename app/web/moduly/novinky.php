<?php

$start = (int)get('start') ?: 0;
$stranka = 20;

foreach(Novinka::zNejnovejsich($start, $stranka) as $n) {
  $t->assign([
    'novinka' =>  $n,
    'text'    =>  $n->typ() == Novinka::BLOG ?
      '<p>'.$n->nahled().'<a href="blog/'.$n->url().'">…více</a></p>' :
      $n->text(),
    'blogn'   =>  $n->typ() == Novinka::BLOG ? 'BLOG: ' : '',
    'datum'   =>  $n->vydat()->format('j.n.'),

  ]);
  $t->parse('novinky.novinka');
}

$t->assign('url', 'novinky?start='.($start + $stranka));
$t->parse('novinky.starsi');

if($start > 0) {
  $novejsi = $start - $stranka;
  $t->assign('url', $novejsi <= 0 ? 'novinky' : 'novinky?start='.$novejsi);
  $t->parse('novinky.novejsi');
}

$this->info()->nazev('Novinky');
