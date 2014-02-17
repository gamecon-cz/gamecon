<?php

$SKRIPT_ZACATEK=microtime(true);

require_once('./scripts/konstanty.hhp'); //lokální konstanty
require_once('./scripts/sloupek.hhp');
require_once($SDILENE_VSE);

//kompatibilita se starými skripty
$db_jmeno=$db_spojeni=null; //znulování db_údajů - přístup do databáze řeší bez nich dbQuery($dotaz)

//přihlášení
if(post('login') && post('heslo'))
{
  if(post('trvale'))
    $u=Uzivatel::prihlasTrvale(post('login'),post('heslo'));
  else
    $u=Uzivatel::prihlas(post('login'),post('heslo'));
  if($u)
  {
    $_SESSION['id_uzivatele']=$u->id(); //kompatibilita se starými skripty
    back();
  }
  else
  {
    chyba(hlaska('chybaPrihlaseni'));
  }
}
$u=Uzivatel::nactiPrihlaseneho();
if(post('odhlas'))
  $u->odhlas(true);

//id_uzivatele pro kompatibilitu s pův. skripty. Kvůli možnému sdílení session
//mezi adminem a webem se řídíme jen novým api, staré hodnoty neodpovídající
//novému api ignorujeme
if($u)
  $_SESSION['id_uzivatele']=$u->id();
elseif(isset($_SESSION["id_uzivatele"])) //uživatel není ale id_uzivatele je - ignorujeme
  unset($_SESSION["id_uzivatele"]);

//určení skriptu, který zpracuje stránku
$obsah='';
$url=new Url('req');
$titulek=new Titulek();
if($url->delka()==0)
{ //titulka - přesměrováváme na novinky
  header('Location: '.URL_WEBU.'/o-gameconu/');
  die();
}
elseif($url->delka() && $stranka=dbOneLineS('SELECT obsah, id_stranky FROM stranky 
  WHERE url_stranky=$0',array($url->cela())))
{ //statická stránka
  $obsah=$stranka['obsah'];
  $titulek->socNahledNajdi($obsah);
  $obsah=Markdown($obsah);
  //TODO vyřešit nějak systematicky
  $obsah=str_replace('<a href="http://','<a onclick="return!window.open(this.href)" href="http://',$obsah);
  $delkaKousku=57;
  $obsah=preg_replace('@(<a href="&#x6d;&#x61;)(&#x69;&#x6c;&#x74;)([^<]{0,'.$delkaKousku.'})([^<]{0,'.$delkaKousku.'})([^<]{0,'.$delkaKousku.'})([^<]{0,'.$delkaKousku.'})([^<]+</a>)@',
    '<script>document.write(\'$1\' + \'$2\' + \'$3\' + \'$4\' + \'$5\' + \'$6\' + \'$7\')</script>', $obsah);
  $extraTridy='str'.$stranka['id_stranky'];
}
elseif( $url->delka()==2 && $aktivita=Aktivita::zUrlViditelna($url->cast(1), $url->cast(0)) )
{ //stránka s nějakou aktivitou
  require_once('./scripts/aktivita.php');
}
elseif($url->delka()>=1 && $url->delka()<=4 && // změnit horní hranici dle potřeby
  (is_file('./scripts/modules/'.$url->cast(0).'.php') || 
  is_file('./scripts/modules/'.$url->cast(0).'/'.$url->cast(0).'.php')))
{ //stránka modulu (výstup se vkládá do hlavní části stránky)
  //rozhraní pro inkludovanou stránku
  $VLASTNI_VYSTUP=false; //zajišťuje si stránka sama výstup? (=nechce být vykreslena do prostředního pole default rozložení stránek)
  $ROOT_DIR=getcwd(); //složka s rootem www
  $MODULE_DIR=$ROOT_DIR.'/scripts/modules'; //složka s rootem modulu
  //plus navíc: $u, $url, $titulek
  ob_start();
  chdir($MODULE_DIR);
  require($url->cast(0).'.php');
  chdir($ROOT_DIR);
  $obsah=ob_get_clean();
  //temp, mozno vyresit lepe
  if($VLASTNI_VYSTUP)
  {
    echo $obsah;
    if($url->cast(0)=='program') profilInfo(); //ruční výjimka pro program (jinak nevíme, jestli negenerujeme např. binární soubor)
    die();
  }
}
else
{ //neexistující stránka
  header('HTTP/1.1 404 Not Found');
  $xtpl=new XTemplate('./templates/neexistujici.xtpl');
  $xtpl->assign('url','http://gamecon.cz/'.$url->cela());
  $xtpl->parse('neexistujici');
  $obsah=$xtpl->text('neexistujici');;
}


//vykreslení stránky

$xtpl=new XTemplate('./templates/hlavni.xtpl');

$menu=new Menu();
$xtpl->assign('menu',$menu->html($_GET['req']));
$sloupek=new Sloupek($menu->seznamHer());
$xtpl->assign('sloupek',$sloupek->zXtpl('./templates/sloupek.xtpl'));
if(!$titulek->stranka() && $menu->aktivniNazev()) //pokud se nenačetl titulek aktivitou, načte se teď dle menu
  $titulek->stranka($menu->aktivniNazev());

if(!$u)
{
  $xtpl->parse('vse.prihlasovani');
  $xtpl->parse('vse.horMenu.prihlaska.default');
}
else
{
  $xtpl->assign('prezdivka',$u->prezdivka());
  $xtpl->assign('uid',$u->id());
  $xtpl->assign('avatar',$u->avatar());
  $xtpl->assign('a',$u->koncA());
  if($u->gcPrihlasen() && REGISTRACE_AKTIVNI)
  {
    $xtpl->assign('finance',$u->finance()->hr());
    $xtpl->parse('vse.prihlasen.finance');
    $xtpl->parse('vse.horMenu.prihlaska.ok');
    $xtpl->parse('vse.horMenu.finance');
  }
  elseif(REGISTRACE_AKTIVNI)
  {
    $xtpl->parse('vse.horMenu.prihlaska.neok');
  }
  if($u->maPravo(ID_PRAVO_ORG_SEKCE))
  {
    $xtpl->assign('adminLink',URL_ADMIN);
    $xtpl->parse('vse.prihlasen.admin');
  }
  elseif($u->maPravo(ID_PRAVO_ORG_AKCI))
  {
    $xtpl->assign('prehledLink',URL_ADMIN.'/muj-prehled');
    $xtpl->parse('vse.prihlasen.mujPrehled');
  }
  $xtpl->parse('vse.prihlasen');
}

if(REGISTRACE_AKTIVNI) $xtpl->parse('vse.horMenu.prihlaska');
/*
if(POCITADLO_VIDITELNE) {
  $xtpl->assign('sekundyPocitadloKonecOdpoctu', date_timestamp_get(date_create(POCITADLO_KONEC_ODPOCTU)));
  $xtpl->assign('htmlPocitadla', htmlPocitadla(POCITADLO_KONEC_ODPOCTU));
  $xtpl->parse('vse.pocitadlo');
}
*/
if(PROGRAM_VIDITELNY)
{ 
  $xtpl->parse('vse.horMenu.program');
  //$xtpl->parse('vse.programLink');
}
$xtpl->parse('vse.horMenu'); 

$xtpl->assign('obsahRetezec',Chyba::vyzvedniHtml().$obsah);
$xtpl->assign('titulekStranky',$titulek->cely());
if(isset($extraTridy)) $xtpl->assign('extraTridy',$extraTridy);
$xtpl->assign('socNahled',$titulek->socNahledHtml());
$xtpl->assign('datum', ((new DateTime(GC_BEZI_OD))->format('j.')).'&ndash;'.((new DateTime(GC_BEZI_DO))->format('j. n. Y')));
$xtpl->assign('base', URL_WEBU.'/');
$xtpl->parse('vse');
$xtpl->out('vse');
profilInfo();
