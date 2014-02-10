<?php 

$SKRIPT_ZACATEK = microtime(true);

require_once('scripts/konstanty.hhp'); //local constants
require_once('scripts/admin-menu.hhp'); //local constants
require_once($SDILENE_VSE);

// nastaví uživatele $u a $uPracovni
require('scripts/prihlaseni.php');

// odesílání bugreportu
if(post('bugreport')) {
  mail(
    'gamecon.external_tasks.101564.godric@app.teambox.com',
    '[bug] '.post('nazev'),
    post('popis')."\n\n(reportoval".$u->koncA()." ".$u->jmenoNick()." - ".$u->mail().")",
    'From: info@gamecon.cz'."\r\n".'Reply-To: info@gamecon.cz'
  );
  oznameni('hlášení chyby odesláno');
}

// xtemplate inicializace
$xtpl=new XTemplate('./templates/main.xtpl');
$xtpl->assign('pageTitle','GameCon – Administrace');

// nastavení stránky, prázdná url => přesměrování na úvod
if(!get('req'))
  back('uvod');
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
    if($uPracovni) {
      $xtpl->assign(array(
        'avatar'  =>  $uPracovni->avatar(),
        'jmeno'   =>  $uPracovni->jmeno(),
        'nick'    =>  $uPracovni->prezdivka(),
        'status'  =>  $uPracovni->statusHtml(),
      ));
      $xtpl->parse('all.uzivatel.vybrany');
    } else {
      $xtpl->parse('all.uzivatel.omnibox');
    }
    $xtpl->parse('all.uzivatel');
  }
  // operátor - info & odhlašování
  $xtpl->assign('a', $u->koncA());
  $xtpl->assign('operator', $u->jmenoNick());
  $xtpl->parse('all.operator');
  // konstrukce stránky
  if(isset($menu[$stranka]) && $u->maPravo($menu[$stranka]['pravo']))
  {
    $_SESSION['id_admin'] = $u->id(); //součást interface starých modulů
    $_SESSION['id_uzivatele'] = $uPracovni ? $uPracovni->id() : null ; //součást interface starých modulů
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
    unset($_SESSION['id_uzivatele']);
    unset($_SESSION['id_admin']);
  }
  elseif(!$u->maPravo($menu[$stranka]['pravo']))
    $xtpl->assign_file('obsah','./templates/zakazano.xtpl'); 
  else
    $xtpl->assign_file('obsah','./templates/404.xtpl');
  // výstup
  $xtpl->assign('protip', $protipy[array_rand($protipy)]);
  $xtpl->parse('all.paticka');
  $xtpl->assign('chyba',chyba::vyzvedniHtml());
  $xtpl->assign('base', URL_ADMIN.'/');
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();  
}
