<?php

/** 
 * Přehled aktivit, které se mají tisknout, a link, který je vytiskne
 *
 * nazev: Tisk prezenčních listů
 * pravo: 103
 */

require_once('prezence.hhp');

$xtpl_temp=$xtpl; //uskladnění glbálního xtpl kvůli zpřístupnění proměnné $xtpl
$xtpl=new XTemplate('tisk.xtpl');

//zpracování data a času
//kopie z prezence.php
//$ted=new DateTime(DEN_PRVNI_DATE.' 13:34:40'); //testování
$ted=new DateTime();
$xtpl->assign('datum',$ted->format('j.n.'));
$xtpl->assign('casAktualni',$ted->format('H:i:s'));
$gcZacatek=new DateTime(DEN_PRVNI_DATE);
$delta=$ted->getTimestamp()-$gcZacatek->getTimestamp(); //rozdíl sekund od začátku GC
if(get('cas'))
{
  $cas=explode('-',get('cas'));
  $den=$cas[0];
  $zacatek=$cas[1];
  $xtpl->parse('tisk.casMan');
}
elseif($delta>0 and $delta<60*60*24*4)
{ //gc zatím asi probíhá, generujeme nejaktuálnější data
  $den=(int)$ted->format('j')-(int)$gcZacatek->format('j')+1;
  $zacatek=(int)$ted->format('G')+1; //chceme aktivity začínající až v následující hodině
  $xtpl->parse('tisk.casAuto');
}
else
{
  $den=0;
  $zacatek=0;
  $xtpl->parse('tisk.nevybrano');
}

//roletková tabulka s manuálním výběrem data a času
//kopie z prezence.php
$i=0;
foreach($PROGRAM_DNY as $d)
{
  for($j=PROGRAM_ZACATEK;$j<PROGRAM_KONEC;$j++)
  {
    $xtpl->assign('cas',$d.' '.$j.':00');
    $xtpl->assign('val',($i+1).'-'.$j);
    if($j==$zacatek && $i+1==$den)
      $xtpl->assign('sel','selected="selected"');
    else
      $xtpl->assign('sel','');
    $xtpl->parse('tisk.cas');
  }
  $i++;
}

//načtení a tisk aktivit
if($zacatek)
{
  $ids=array();
  $aktivity='';
  $a=dbQuery('
    SELECT *
    FROM akce_seznam
    WHERE rok='.ROK.' AND zacatek='.$zacatek.' AND den='.$den.' 
    AND (stav=1 || stav=2) AND '.$typySql);
  while($r=mysql_fetch_assoc($a))
  {
    $ids[]=$r['id_akce'];
    $xtpl->assign($r);
    $xtpl->assign('cas_akce',datum2($r));
    $xtpl->parse('tisk.aktivity.aktivita');
    //$aktivity.='<tr><td>'.$r['nazev_akce'].'</td><td>'.datum2($r).'</tr></td>';
  }
  if($ids)
  {
    $xtpl->assign('ids',implode(',',$ids));
    $xtpl->parse('tisk.aktivity');
  }
  else
    $xtpl->parse('tisk.zadneAktivity');
}

$xtpl->parse('tisk');
$xtpl->out('tisk');
$xtpl=$xtpl_temp;

?>
