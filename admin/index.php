<?php 

$SKRIPT_ZACATEK=microtime(true);

require_once('./scripts/konstanty.hhp'); //local constants
require_once('./scripts/admin-menu.hhp'); //local constants
require_once($SDILENE_VSE);

/* --- hlavni program --- */

//přihlášení
if(post('loginNAdm') && post('hesloNAdm'))
{
  if($u=Uzivatel::prihlas(post('loginNAdm'),post('hesloNAdm')))
    back();
  else
    back(); //todo: ošetření chyby obecným způsobem
}
$u=Uzivatel::nactiPrihlaseneho();
if(post('odhlasNAdm'))
  $u->odhlas(true);

//staré zpracování výběru uživatele pro admin. práci rozšířené o OOP
$uPracovni=null;
require_once('./scripts/vyber-uzivatele-old.hhp');

//xtemplate inicializace
$xtpl=new XTemplate('./templates/main.xtpl');
$xtpl->assign('pageTitle','GameCon – Administrace');

if(!get('req'))
  back('/uvod'); //nastavení stránky, prázdná url => přesměrování na úvod
$req=explode('/',get('req'));
$stranka=$req[0];
$podstranka=isset($req[1])?$req[1]:'';
// zobrazení stránky
if(!$u)
{
  $xtpl->assign_file('obsah','./templates/prihlaseni.xtpl');
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();
}
elseif(is_file('./scripts/zvlastni/'.$stranka.'.php'))
{
  chdir('./scripts/zvlastni/');
  require($stranka.'.php');
}
elseif(is_file('./scripts/zvlastni/'.$stranka.'/'.$podstranka.'.php'))
{
  chdir('./scripts/zvlastni/'.$stranka);
  require($podstranka.'.php');
}
else
{
  // konstrukce menu
  $menu=new AdminMenu('./scripts/modules/');
  $menu=$menu->pole();
  foreach($menu as $url=>$polozka)
  {
    if($u->maPravo($polozka['pravo']))
    {
      $xtpl->assign('url',$url);
      $xtpl->assign('nazev',$polozka['nazev']);
      $xtpl->assign('aktivni',$stranka==$url?'class="active"':'');
      $xtpl->parse('all.menuPolozka');
    }
  }
  // konstrukce submenu
  if(isset($menu[$stranka]['submenu']) && $menu[$stranka]['submenu'])
  {
    $submenu=new AdminMenu('./scripts/modules/'.$stranka.'/');
    $submenu=$submenu->pole();
    foreach($submenu as $url=>$polozka)
    if($u->maPravo($polozka['pravo']))
    {
      $xtpl->assign('url',$url==$stranka?$url:$stranka.'/'.$url);
      $xtpl->assign('nazev',$polozka['nazev']);
      $xtpl->parse('all.submenu.polozka');
    }
    $xtpl->parse('all.submenu');
  }
  // výběr uživatele
  if($u->maPravo(100)) // panel úvod - fixme magická konstanta
  {
    ob_start();
    require('./scripts/modules/omnibox.hhp');
    $xtpl->assign('omnibox',ob_get_clean());
  }
  else
    $xtpl->assign('omnibox','<br><br>');
  // konstrukce stránky
  if(isset($menu[$stranka]) && $u->maPravo($menu[$stranka]['pravo']))
  {
    $_SESSION['id_admin']=$u->id(); //součást interface starých modulů
    $cwd=getcwd(); //uložíme si aktuální working directory pro pozdější návrat
    if(isset($submenu))
    {
      chdir('./scripts/modules/'.$stranka.'/');
      $soubor=$podstranka?$cwd.'/'.$submenu[$podstranka]['soubor']:$cwd.'/'.$submenu[$stranka]['soubor'];
    }
    else
    {
      chdir('./scripts/modules/');
      $soubor=$cwd.'/'.$menu[$stranka]['soubor'];
    }
    ob_start(); //výstup uložíme do bufferu
    require($soubor);
    $xtpl->assign('obsahRetezec',ob_get_clean());
    chdir($cwd);
  }
  elseif(!$u->maPravo($menu[$stranka]['pravo']))
    $xtpl->assign_file('obsah','./templates/zakazano.xtpl'); 
  else
    $xtpl->assign_file('obsah','./templates/404.xtpl');
  // výstup
  $xtpl->parse('all.odhlasovani');
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();  
}

?>
