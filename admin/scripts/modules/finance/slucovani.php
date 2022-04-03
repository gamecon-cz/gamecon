<?php

/**
 * nazev: Slučování uživatelů
 * pravo: 108
 * submenu_group: 5
 */

$t = new XTemplate('slucovani.xtpl');

// provede sloučení uživatelů
if(post('sloucit')) {
  if(post('skrytyU1') < post('skrytyU2')) {
    $new = Uzivatel::zId(post('skrytyU1'));
    $old = Uzivatel::zId(post('skrytyU2'));
  } else {
    $new = Uzivatel::zId(post('skrytyU2'));
    $old = Uzivatel::zId(post('skrytyU1'));
  }

  $zmeny = [];
  if(post('login') == $old->id()) $zmeny[] = 'login_uzivatele';
  if(post('heslo') == $old->id()) $zmeny[] = 'heslo_md5';
  if(post('mail') == $old->id())  $zmeny[] = 'email1_uzivatele';
  if(post('jmeno') == $old->id()) {
    $zmeny[] = 'jmeno_uzivatele';
    $zmeny[] = 'prijmeni_uzivatele';
  }
  if(post('adresa') == $old->id()) {
    $zmeny[] = 'ulice_a_cp_uzivatele';
    $zmeny[] = 'mesto_uzivatele';
    $zmeny[] = 'stat_uzivatele';
    $zmeny[] = 'psc_uzivatele';
  }
  if(post('telefon') == $old->id())  $zmeny[] = 'telefon_uzivatele';
  if(post('datum_narozeni') == $old->id()) $zmeny[] = 'datum_narozeni';
  if(post('pohlavi') == $old->id())  $zmeny[] = 'pohlavi';
  if(post('poznamka') == $old->id()) $zmeny[] = 'poznamka';
  if(post('op') == $old->id())  $zmeny[] = 'op';

  $new->sluc($old, $zmeny);
  oznameni('Uživatelé sloučeni, nové id: ' . $new->id() . ' - staré id: ' . $old->id());
}

// připraví / předvyplní form pro sloučení uživatelů
if(post('pripravit')) {
  // kontrola prázdného id
  if(post('u1') == null || post('u2') == null) {
    chyba('Zadejte obě id');
  }

  if(post('u1') === post('u2')) {
    chyba('Slučujete stejná id');
  }

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
