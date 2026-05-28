<?php

//přibližná kopie z prezence.php
$ted=new DateTime();
//$ted=new DateTime('2013-07-20 13:34:40'); //testování
$xtpl->assign('datum',$ted->format('j.n.'));
$xtpl->assign('casAktualni',$ted->format('H:i:s'));
$gcZacatek=new DateTime(DEN_PRVNI_DATE);
$delta=$ted->getTimestamp()-$gcZacatek->getTimestamp(); //rozdíl sekund od začátku GC
if(1)
{ //gc zatím asi probíhá, generujeme nejaktuálnější data
  $den=(int)$ted->format('j')-(int)$gcZacatek->format('j')+1;
  $zacatek=(int)$ted->format('G')+1; //chceme aktivity začínající až v následující hodině
}
//konec kopie

$xtpl=new XTemplate('last-minute-tabule.xtpl');

//zaokrouhlení zobrazení času na bloky
if($zacatek<=9) $zacatek=9;
elseif($zacatek<=14) $zacatek=14;
elseif($zacatek<=19) $zacatek=19;
else
{
  $zacatek=9;
  $den++;
  $xtpl->assign('zitra','zítra');
}

$o=aktivitySPocty('den='.$den.' AND zacatek='.$zacatek.' AND rok='.ROK,null,null,'pocet<kapacita_celkova');
while($r=mysql_fetch_assoc($o))
{
  $a=new Aktivita($r);
  $xtpl->assign(array('nazev'=>$a->nazev(),'obsazenost'=>$a->obsazenostHtml()));
  $xtpl->parse('tabule.aktivita');
}
if(mysql_num_rows($o)==0)
  $xtpl->parse('tabule.nic');
$zoom=empty($_GET['zoom'])?100:(int)$_GET['zoom'];
$xtpl->assign('lupa',$zoom);
$xtpl->assign('lupaPlus',$zoom+10);
$xtpl->assign('lupaMinus',$zoom-10);
$xtpl->assign('programCss',Program::cssRetezec());
$xtpl->assign('cas',$zacatek.':00');
$xtpl->parse('tabule');
$xtpl->out('tabule');

?>
