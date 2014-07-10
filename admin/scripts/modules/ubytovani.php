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


$t = new XTemplate('ubytovani.xtpl');

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
