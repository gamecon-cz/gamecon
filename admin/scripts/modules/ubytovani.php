<?php

/** 
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Ubytování
 * pravo: 101
 */

if(isset($_POST['pokoje']))
{
  if(empty($_FILES['pokcsv']['tmp_name']))
    die('soubor nenačten');
  $f=fopen($_FILES['pokcsv']['tmp_name'],'r');
  $q='INSERT INTO ubytovani(id_uzivatele,den,pokoj,rok) VALUES '."\n";
  while($r=fgetcsv($f,512,';'))
  {
    if(count($r)<2 || count($r)>6) die('nesprávný počet sloupců');
    //var_dump($r);
    $uid=$r[0];
    unset($r[0]);
    foreach($r as $i=>$pokoj)
      if($pokoj)
        $q.='('.$uid.','.($i-1).','.$pokoj.','.ROK."),\n";
  }
  $q=substr($q,0,-2).';';
  fclose($f);
  //echo '<pre>';
  $ok=1;
  $ok&=dbQuery('DELETE FROM ubytovani WHERE rok='.ROK.";\n");
  $ok&=dbQuery($q);
  if(!$ok) die();
  chyba('Soubor načten');
}

if(isset($_POST['pridelitPokoj'])) {
  Pokoj::ubytujNaCislo(Uzivatel::zId(post('uid')), post('pokoj'));
  back();
}

$hlaska=Chyba::vyzvedni();

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
