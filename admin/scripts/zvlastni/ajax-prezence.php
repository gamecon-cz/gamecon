<?php

function aPrezenceFail($zprava)
{
  die('{"uspech":false, "zprava":"'.$zprava.'"}');
}

$dorazil=get('dorazil');
$akceId=(int)get('id_akce');
if(!$akceId)
  aPrezenceFail('Nezjištěno ID akce. Epic fail.');
if(!$dorazil)
  aPrezenceFail('Aktivitu s 0 účastníky nelze uložit.');

$bPrihl=0x01; //bitová maska přihlášen
$bUcast=0x02; //bitová maska účast

$uspech=false;
$zprava='';
//vytvoří se seznam uživatelů a do hodnot se zanese jestli byli přihlášeni a přišli (bitově)
$uzivatele=array();
$a=dbQueryS('SELECT * 
  FROM akce_prihlaseni WHERE id_akce=$0',array($akceId));
while($r=mysql_fetch_assoc($a))
{
  $uzivatele[$r['id_uzivatele']]=$bPrihl;
  if($r['id_stavu_prihlaseni']!=='0')
    aPrezenceFail('Zřejmě byla prezence už vyplněna, ignorováno.');
}
foreach($dorazil as $id=>$i)
  @$uzivatele[$id]|=$bUcast; //zavináč potlačí chyby
//var_dump($uzivatele); //přehled situace
//zpracují se updaty
$nahradniciSql=array();
$doraziliSql=array();
$nedoraziliSql=array();
$nedoraziliSql1=array();
$dorazilPocet=0;
foreach($uzivatele as $id=>$stav)
{
  if($stav&$bPrihl)
  {
    if($stav&$bUcast)
    { //dorazil v pořádku
      $doraziliSql[]='id_uzivatele='.$id;
      $dorazilPocet++;
    }
    else
    { //nedorazil
      $nedoraziliSql[]='id_uzivatele='.$id;
      $nedoraziliSql1[]='('.$akceId.','.$id.',3)';
      $nedoraziliSql2[]='('.$akceId.','.$id.',"nedostaveni_se")';
    }
  }
  else if($stav&$bUcast)
  { //náhradník
    $nahradniciSql[]='('.$akceId.','.$id.',2)';
    $dorazilPocet++;
  }
  else
  {
    aPrezenceFail('Přijata kombinace "nepřihlášen, nedorazil", systémová chyba.');
  } 
}
if($nahradniciSql)
  dbQuery('INSERT INTO akce_prihlaseni (id_akce,id_uzivatele,id_stavu_prihlaseni) VALUES '."\n  ".
    implode(",\n  ",$nahradniciSql).";\n"); //náhradníci
if($doraziliSql)
  dbQuery('UPDATE akce_prihlaseni SET id_stavu_prihlaseni=1 WHERE ('.implode(' || ',$doraziliSql).') AND id_akce='.$akceId.";\n");
if($nedoraziliSql)
{
  dbQuery('DELETE FROM akce_prihlaseni WHERE ('.implode(' || ',$nedoraziliSql).') AND id_akce='.$akceId.";\n"); 
  dbQuery('INSERT INTO akce_prihlaseni_spec (id_akce,id_uzivatele,id_stavu_prihlaseni) VALUES'."\n  ".implode(",\n  ",$nedoraziliSql1).";\n");
  dbQuery('INSERT INTO akce_prihlaseni_log (id_akce,id_uzivatele,typ) VALUES'."\n  ".implode(",\n  ",$nedoraziliSql2).";\n");
}

echo '{"uspech":true, "zprava":"Uloženo OK s účastí '.$dorazilPocet.'/'.get('kapacita_celkova').'"}';

?>
