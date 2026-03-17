<?php

/**
 * @var \Gamecon\XTemplate\XTemplate $t
 */

$this->blackarrowStyl(true);

$start = (int)get('start') ?: 0;
$naStranku = 20;

$novinky = Novinka::zNejnovejsich($start, $naStranku);

foreach($novinky as $n) {
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

if(count($novinky) >= $naStranku) {
  $t->assign('url', 'novinky?start='.($start + $naStranku));
  $t->parse('novinky.starsi');
}

if($start > 0) {
  $novejsi = $start - $naStranku;
  $t->assign('url', $novejsi <= 0 ? 'novinky' : 'novinky?start='.$novejsi);
  $t->parse('novinky.novejsi');
}

$this->info()->nazev('Novinky');
