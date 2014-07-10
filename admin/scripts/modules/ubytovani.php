<?php

/** 
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Ubytování
 * pravo: 101
 */


if(post('pokojeImport')) {
  $f = fopen($_FILES['pokojeSoubor']['tmp_name'], 'r');
  if(!$f) throw new Exception('Soubor se nepodařilo načíst');

  $hlavicka = array_flip(fgetcsv($f, 512, ";"));
  if(!array_key_exists('id_uzivatele', $hlavicka)) throw new Exception('Nepodařilo se zpracovat soubor');
  $uid = $hlavicka['id_uzivatele'];
  $od = $hlavicka['prvni_noc'];
  $do = $hlavicka['posledni_noc'];
  $pokoj = $hlavicka['pokoj'];

  dbDelete('ubytovani', array('rok' => ROK));

  while($r = fgetcsv($f, 512, ";")) {
    if($r[$pokoj]) {
      for($den = $r[$od]; $den <= $r[$do]; $den++) {
        dbInsert('ubytovani', array(
          'id_uzivatele'  =>  $r[$uid],
          'den'           =>  $den,
          'pokoj'         =>  $r[$pokoj],
          'rok'           =>  ROK,
        ));
      }
    }
  }

  oznameni('import dokončen');
}


if(isset($_POST['pridelitPokoj'])) {
  Pokoj::ubytujNaCislo(Uzivatel::zId(post('uid')), post('pokoj'));
  back();
}


if(post('prohozeniProvest')) {
  var_dump($_POST);
  $u1 = Uzivatel::zId(post('u1'));
  $u2 = Uzivatel::zId(post('u2'));
  $a1 = Aktivita::zId(post('a1'));
  $a2 = Aktivita::zId(post('a2'));
  $a1->odhlas($u1, BEZ_POKUT);
  $a2->odhlas($u2, BEZ_POKUT);
  $a1 = Aktivita::zId(post('a1')); // hack znovunačtení kvůli chybějícímu invalidate v aktivitě
  $a2 = Aktivita::zId(post('a2'));
  $a1->prihlas($u2);
  $a2->prihlas($u1);
  oznameni('Aktivity prohozeny');
}


$t = new XTemplate('ubytovani.xtpl');


if(post('prohozeniNacist')) {
  $t->assign($_POST);
  foreach(array('u1', 'u2') as $name) {
    $ux = Uzivatel::zId(post($name));
    foreach(Aktivita::zUzivatele($ux) as $a) {
      if(!$a->teamova() && $a->prihlasovatelna()) {
        $t->assign('a', $a);
        $t->parse('ubytovani.a'.$name);
      }
    }
  }
}


$pokoj = Pokoj::zCisla(get('pokoj'));
$ubytovani = $pokoj ? $pokoj->ubytovani() : array();
$t->assign(array(
  'uid'       =>  $uPracovni ? $uPracovni->id() : '',
  'pokoj'     =>  get('pokoj'),
  'ubytovani' =>  array_uprint($ubytovani, function($e){
    $ne = $e->gcPritomen() ? '' : 'ne';
    $color = $ne ? '#f00' : '#0a0';
    $a = $e->koncA();
    return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
  }, '<br>'),
));
$t->parse('ubytovani');
$t->out('ubytovani');
