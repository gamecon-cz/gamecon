<?php

use \Gamecon\Cas\DateTimeCz;

$xtpl=new XTemplate(__DIR__.'/last-minute-tabule.xtpl');

$test=null; //debug
if(date('Y')!=ROK) //fix pro datum z špatných let
  $test=ROK.'-01-01 01:00';
$od=(new DateTimeCz($test))->sub(new DateInterval('PT15M'));
$do=
  (int)(new DateTimeCz($test))->format('G') < 20 ? //před 20:00 vypisovat 4h dopředu, potom už další den
  (new DateTimeCz($test))->add(new DateInterval('PT3H45M')) :
  (new DateTimeCz($test))->add(new DateInterval('P1D'))->setTime(9,0) ;
$posledniBlok=null;
foreach(Aktivita::zRozmezi($od, $do, Aktivita::JEN_VOLNE | Aktivita::VEREJNE) as $a)
{
  if($posledniBlok && $posledniBlok!=$a->zacatek()->format('z'))
    $xtpl->parse('tabule.blok');
  $xtpl->assign([
    'nazev'       =>  $a->nazev(),
    'obsazenost'  =>  $a->obsazenostHtml(),
    'cas'         =>  $a->zacatek()->format('G:i'),
    'zitra'       =>  $a->zacatek()->rozdilDne($od)
  ]);
  $xtpl->parse('tabule.blok.aktivita');
  $posledniBlok=$a->zacatek()->format('z');
}
if(!$posledniBlok)
{
  $xtpl->assign('cas',$od->zaokrouhlitNaHodinyNahoru()->format('G:i'));
  $xtpl->parse('tabule.blok.nic');
}
$xtpl->parse('tabule.blok');

$zoom=empty($_GET['zoom'])?100:(int)$_GET['zoom'];
$xtpl->assign('lupa',$zoom);
$xtpl->assign('lupaPlus',$zoom+10);
$xtpl->assign('lupaMinus',$zoom-10);

$xtpl->assign('programCss',Program::cssRetezec());
$xtpl->parse('tabule');
$xtpl->out('tabule');
