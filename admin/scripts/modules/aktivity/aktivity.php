<?php

/** 
 * Stránka pro tvorbu a správu aktivit. Povětšinou starý kód
 *
 * nazev: Aktivity
 * pravo: 102
 */
 
if(post('filtr'))
{
  if(post('filtr')=='vsechno')
    unset($_SESSION['adminAktivityFiltr']);
  else
    $_SESSION['adminAktivityFiltr']=post('filtr');
  back();
}

if(post('smazat'))
{
  aktivitaZrus(post('aktivitaId'));
  back();
}

if(post('publikovat'))
{
  dbQueryS('UPDATE akce_seznam SET stav=4 WHERE id_akce=$0',
    array(post('aktivitaId')));
  back();
}

if(post('aktivovat'))
{
  dbQueryS('UPDATE akce_seznam SET stav=1 WHERE id_akce=$0',
    array(post('aktivitaId')));
  back();
}

if(post('aktivovatVse'))
{
  dbQueryS('UPDATE akce_seznam SET stav=1 WHERE stav=4',
    array(post('aktivitaId')));
  back();
}

if(post('instance'))
{
  aktivitaInstanciovat(post('aktivitaId'));
  back();
}

$tpl=new XTemplate('aktivity.xtpl');

//zpracování filtru
$filtr=isset($_SESSION['adminAktivityFiltr'])?
  $_SESSION['adminAktivityFiltr']:'';
$varianty=array(
  'a'=>array('popis'=>'deskovky',   'db'=>'t.id_typu=1'),
  'b'=>array('popis'=>'larpy',      'db'=>'t.id_typu=2'),
  'c'=>array('popis'=>'RPG',        'db'=>'t.id_typu=4'),
  'd'=>array('popis'=>'přednášky',  'db'=>'t.id_typu=3'),
  'e'=>array('popis'=>'dílny',      'db'=>'t.id_typu=5'),
  'f'=>array('popis'=>'bonusy',     'db'=>'t.id_typu=7'),
  'g'=>array('popis'=>'wargaming',  'db'=>'t.id_typu=6'),
  'h'=>array('popis'=>'legendy',    'db'=>'t.id_typu=8'),
  );
foreach($varianty as $k => $v)
{
  $tpl->assign('val',$k);
  $tpl->assign('nazev',ucfirst($v['popis']));
  $tpl->assign('sel',$filtr==$k?'selected="selected"':'');
  $tpl->parse('aktivity.filtrMoznost');
}
if($filtr) $filtr=' AND '.$varianty[$filtr]['db'].' ';

//načtení aktivit a zpracování
if(get('sort')) //řazení
  setcookie('akceRazeni',get('sort'),time()+365*24*60*60) xor
  back();
$akceRazeni=isset($_COOKIE['akceRazeni'])&&$_COOKIE['akceRazeni'] ? $_COOKIE['akceRazeni'] : 'nazev_akce';
$a=dbQuery('SELECT *, den*100+zacatek as cas FROM akce_seznam a
  LEFT JOIN uzivatele_hodnoty u ON(u.id_uzivatele=a.organizator)
  LEFT JOIN akce_lokace l ON(a.lokace=l.id_lokace)
  LEFT JOIN akce_typy t ON(a.typ=t.id_typu)
  WHERE a.rok='.$GLOBALS['ROK_AKTUALNI'].'
  '.$filtr.'
  ORDER BY '.dbCol($akceRazeni).', nazev_akce, cas' );

$typy=array();
while($r=mysql_fetch_assoc($a))
{
  $tpl->assign($r);
  $tpl->assign('cas',datum2($r));
  if($r['patri_pod']) $tpl->parse('aktivity.aktivita.instSymbol');
  if($r['stav']==0) $tpl->parse('aktivity.aktivita.tlacitka.publikovat');
  if($r['stav']==4) $tpl->parse('aktivity.aktivita.tlacitka.aktivovat'); 
  $tpl->parse('aktivity.aktivita.tlacitka');
  $tpl->parse('aktivity.aktivita');
  isset($typy[$r['typ_1p']])?$typy[$r['typ_1p']]++:$typy[$r['typ_1p']]=1;
}

$typStat=array();
unset($typy['']);
foreach($typy as $typ=>$pocet)
  $typStat[]='<b>'.ucfirst($typ).':</b> '.$pocet;
$tpl->assign('statistika',implode($typStat,' | '));
if(!$filtr)
  $tpl->parse('aktivity.aktivovatVse');

$tpl->parse('aktivity');
$tpl->out('aktivity');

?>
