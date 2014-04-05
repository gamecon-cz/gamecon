<?php

/** 
 * Stránka pro tvorbu a správu aktivit.
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
  Aktivita::zId(post('aktivitaId'))->instanciuj();
  back();
}

$tpl=new XTemplate('aktivity.xtpl');

//zpracování filtru
$filtr=isset($_SESSION['adminAktivityFiltr'])?
  $_SESSION['adminAktivityFiltr']:'';
$varianty=array(
  'a'=>array('popis'=>'deskovky',   'db'=>'1'),
  'b'=>array('popis'=>'larpy',      'db'=>'2'),
  'c'=>array('popis'=>'RPG',        'db'=>'4'),
  'd'=>array('popis'=>'přednášky',  'db'=>'3'),
  'e'=>array('popis'=>'dílny',      'db'=>'5'),
  'f'=>array('popis'=>'bonusy',     'db'=>'7'),
  'g'=>array('popis'=>'wargaming',  'db'=>'6'),
  'h'=>array('popis'=>'legendy',    'db'=>'8'),
  );
foreach($varianty as $k => $v)
{
  $tpl->assign('val',$k);
  $tpl->assign('nazev',ucfirst($v['popis']));
  $tpl->assign('sel',$filtr==$k?'selected="selected"':'');
  $tpl->parse('aktivity.filtrMoznost');
}

//načtení aktivit a zpracování
if(get('sort')) { //řazení
  setcookie('akceRazeni',get('sort'),time()+365*24*60*60);
  back();
}
$razeni = empty($_COOKIE['akceRazeni']) ? 'nazev_akce' : $_COOKIE['akceRazeni'];
$razeni = array($razeni, 'nazev_akce', 'zacatek');

$filtr = empty($filtr) ? array() : array('typ' => $varianty[$filtr]['db']);
$filtr = array_merge(array('rok' => ROK), $filtr);

$aktivity = Aktivita::zFiltru($filtr, $razeni);

foreach($aktivity as $a)
{
  $r = $a->rawDb();
  $tpl->assign(array(
    'id_akce'   => $a->id(),
    'nazev'     => $a->nazev(),
    'cas'       => $a->denCas(),
    'organizatori' => implode(', ', array_map(function($org){ return $org->jmenoNick(); }, $a->organizatori())),
    // TODO fixnout s lepším ORM
    'typ'       => dbOneCol("SELECT typ_1p FROM akce_typy WHERE id_typu = $r[typ]"),
    'mistnost'  => dbOneCol("SELECT nazev_interni FROM akce_lokace WHERE id_lokace = $r[lokace]"),
  ));
  if($r['patri_pod']) $tpl->parse('aktivity.aktivita.instSymbol');
  if($r['stav']==0) $tpl->parse('aktivity.aktivita.tlacitka.publikovat');
  if($r['stav']==4) $tpl->parse('aktivity.aktivita.tlacitka.aktivovat');
  $tpl->parse('aktivity.aktivita.tlacitka');
  $tpl->parse('aktivity.aktivita');
}

if(!$filtr)
  $tpl->parse('aktivity.aktivovatVse');

$tpl->parse('aktivity');
$tpl->out('aktivity');
