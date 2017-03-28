<?php

require __DIR__ . '/../nastaveni/zavadec.php';

require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin
require_once __DIR__ . '/scripts/admin-menu.php'; // třída administračního menu

if(HTTPS_ONLY) httpsOnly();

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
$xtpl->assign([
  'pageTitle' =>  'GameCon – Administrace',
  'base'      =>  URL_ADMIN.'/',
]);

// nastavení stránky, prázdná url => přesměrování na úvod
if(!get('req'))
  back('uvod');
$req=explode('/',get('req'));
$stranka=$req[0];
$podstranka=isset($req[1])?$req[1]:'';

// zobrazení stránky
if(!$u && !in_array($stranka, ['last-minute-tabule', 'program-obecny']))
{
  $xtpl->parse('all.prihlaseni');
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
  // načtení menu
  $menu=new AdminMenu('./scripts/modules/');
  $menu=$menu->pole();
  // načtení submenu
  $submenu = [];
  if(isset($menu[$stranka]['submenu']) && $menu[$stranka]['submenu'])
  {
    $submenu=new AdminMenu('./scripts/modules/'.$stranka.'/');
    $submenu=$submenu->pole();
  }
  // konstrukce stránky
  if(isset($menu[$stranka]) && $u->maPravo($menu[$stranka]['pravo']))
  {
    $_SESSION['id_admin'] = $u->id(); //součást interface starých modulů
    $_SESSION['id_uzivatele'] = $uPracovni ? $uPracovni->id() : null ; //součást interface starých modulů
    $cwd=getcwd(); //uložíme si aktuální working directory pro pozdější návrat
    if(isset($submenu) && $submenu)
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
    $xtpl->parse('all.nenalezeno');
  else
    $xtpl->parse('all.zakazano');
  // operátor - info & odhlašování
  $xtpl->assign('a', $u->koncA());
  $xtpl->assign('operator', $u->jmenoNick());
  $xtpl->parse('all.operator');
  // výběr uživatele
  if($u->maPravo(100)) // panel úvod - fixme magická konstanta
  {
    if($uPracovni) {
      $xtpl->assign([
        'avatar'  =>  $uPracovni->avatar(),
        'jmeno'   =>  $uPracovni->jmeno(),
        'nick'    =>  $uPracovni->prezdivka(),
        'status'  =>  $uPracovni->statusHtml(),
      ]);
      $xtpl->parse('all.uzivatel.vybrany');
    } else {
      $xtpl->parse('all.uzivatel.omnibox');
    }
    $xtpl->parse('all.uzivatel');
  }
  // výstup menu
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
  // výstup submenu
  foreach($submenu as $url=>$polozka)
  {
    if($u->maPravo($polozka['pravo']))
    {
      $xtpl->assign('url',$url==$stranka?$url:$stranka.'/'.$url);
      $xtpl->assign('nazev',$polozka['nazev']);
      $xtpl->parse('all.submenu.polozka');
    }
  }
  $xtpl->parse('all.submenu');
  // výstup
  $xtpl->assign('protip', $protipy[array_rand($protipy)]);
  $xtpl->parse('all.paticka');
  $xtpl->assign('chyba', chyba::vyzvedniHtml());
  $xtpl->assign('jsVyjimkovac', Vyjimkovac::js(URL_WEBU.'/ajax-vyjimkovac'));
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();  
}
