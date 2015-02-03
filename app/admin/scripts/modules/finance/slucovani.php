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
  $zmeny = array();
  if(post('login') == $old->id()) $zmeny[] = 'login_uzivatele';
  if(post('heslo') == $old->id()) $zmeny[] = 'heslo_md5';
  if(post('mail') == $old->id())  $zmeny[] = 'email1_uzivatele';
  $new->sluc($old, $zmeny);
  back();
}

// připraví / předvyplní form pro sloučení uživatelů
if(post('pripravit')) {
  $a = Uzivatel::zId(post('u1'));
  $b = Uzivatel::zId(post('u2'));
  $t->assign(array(
    'uaid'  =>  $a->id(),
    'ubid'  =>  $b->id(),
    'ua'    =>  $a,
    'ub'    =>  $b,
    'amrtvy' => $a->mrtvyMail() ? '(mrtvý)' : '',
    'bmrtvy' => $b->mrtvyMail() ? '(mrtvý)' : '',
  ));
  $t->parse($a->gcPrihlasen() ? 'slucovani.detaily.aPrihlasen' : 'slucovani.detaily.aNeprihlasen');
  $t->parse($b->gcPrihlasen() ? 'slucovani.detaily.bPrihlasen' : 'slucovani.detaily.bNeprihlasen');
  $t->parse('slucovani.detaily');
}

$t->parse('slucovani');
$t->out('slucovani');
