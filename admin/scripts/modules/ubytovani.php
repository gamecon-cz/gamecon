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

if(isset($_POST['pridelitPokoj']))
{
  $pokoj=$_POST['pokoj'];
  $uid=$_POST['uid'];
  $o=dbQuery('SELECT ubytovani_den 
  FROM shop_nakupy n
  JOIN shop_predmety p USING(id_predmetu) 
  WHERE n.id_uzivatele='.(int)$uid.' AND n.rok='.ROK.' AND p.typ=2 ');
  dbQuery('DELETE FROM ubytovani WHERE rok='.ROK.' AND id_uzivatele='.$uid);
  $q='INSERT INTO ubytovani(id_uzivatele,den,pokoj,rok) VALUES '."\n";
  while($r=mysql_fetch_assoc($o))
    $q.='('.$uid.','.$r['ubytovani_den'].','.$pokoj.','.ROK."),\n";
  $q=substr($q,0,-2).';';
  dbQuery($q);
  back();
}

$hlaska=Chyba::vyzvedni();

$t = new XTemplate('ubytovani.xtpl');

$ubytovani = Uzivatel::zIds(dbOneCol('SELECT GROUP_CONCAT(id_uzivatele) FROM ubytovani WHERE rok = $1 AND pokoj = $2', array(ROK, get('pokoj'))));
$t->assign(array(
  'uid'       =>  $uPracovni ? $uPracovni->id() : '',
  'pokoj'     =>  get('pokoj'),
  'ubytovani' =>  array_uprint($ubytovani, function($e){ return $e->jmenoNick(); }, '<br>'),
));
$t->parse('ubytovani');
$t->out('ubytovani');
