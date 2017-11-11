<?php

/**
 * nazev: Slučování uživatelů
 * pravo: 108
 */

$t = new XTemplate('slucovani.xtpl');

// provede sloučení uživatelů
if(post('sloucit')) {
  $new = Uzivatel::zId(post('id'));
  $old = Uzivatel::zId( post('id') == post('u2') ? post('u1') : post('u2') );
  $zmeny = [];
  if(post('login') == $old->id()) $zmeny[] = 'login_uzivatele';
  if(post('heslo') == $old->id()) $zmeny[] = 'heslo_md5';
  if(post('mail') == $old->id())  $zmeny[] = 'email1_uzivatele';
  $new->sluc($old, $zmeny);
  oznameni('Uživatelé sloučeni, nové id ' . $new->id() . '.');
}

// připraví / předvyplní form pro sloučení uživatelů
if(post('pripravit')) {
  $a = Uzivatel::zId(post('u1'));
  $b = Uzivatel::zId(post('u2'));

  $t->assign([
    'uaid'  =>  $a->id(),
    'ubid'  =>  $b->id(),
    'ua'    =>  $a,
    'ub'    =>  $b,
    'amrtvy' => $a->mrtvyMail() ? '(mrtvý)' : '',
    'bmrtvy' => $b->mrtvyMail() ? '(mrtvý)' : '',
  ]);

  for($rok = 2009; $rok <= ROK; $rok++) {
    $t->assign('rok', $rok);
    $t->parse(
      in_array($rok, $a->historiePrihlaseni()) ?
      'slucovani.detaily.historiePrihlaseni.aPrihlasen' :
      'slucovani.detaily.historiePrihlaseni.aNeprihlasen'
    );
    $t->parse(
      in_array($rok, $b->historiePrihlaseni()) ?
      'slucovani.detaily.historiePrihlaseni.bPrihlasen' :
      'slucovani.detaily.historiePrihlaseni.bNeprihlasen'
    );
    $t->parse('slucovani.detaily.historiePrihlaseni');
  }

  $t->parse('slucovani.detaily');
}

$t->parse('slucovani');
$t->out('slucovani');
