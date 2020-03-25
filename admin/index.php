<?php

require __DIR__ . '/../nastaveni/zavadec.php';

require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin
require_once __DIR__ . '/scripts/admin-menu.php'; // třída administračního menu

if(HTTPS_ONLY) httpsOnly();

// nastaví uživatele $u a $uPracovni
require('scripts/prihlaseni.php');

// xtemplate inicializace
$xtpl=new XTemplate('./templates/main.xtpl');
$xtpl->assign([
  'pageTitle' =>  'GameCon – Administrace',
  'base'      =>  URL_ADMIN.'/',
  'cssVersions' => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/files/design', 'css'),
]);

// nastavení stránky, prázdná url => přesměrování na úvod
if(!get('req'))
  back('uvod');
$req = explode('/', get('req'));
$stranka = $req[0];
$podstranka = isset($req[1]) ? $req[1] : '';

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
  $menuObject = new AdminMenu('./scripts/modules/');
  $menu = $menuObject->pole();

  // načtení submenu
  $submenu = [];
  if(isset($menu[$stranka]['submenu']) && $menu[$stranka]['submenu'])
  {
    $submenuObject = new AdminMenu('./scripts/modules/'.$stranka.'/');
    $submenu = $submenuObject->pole();
  }

  // zjištění práv na zobrazení stránky
  $strankaExistuje = isset($menu[$stranka]);
  $podstrankaExistuje = isset($submenu[$podstranka]);
  $uzivatelMaPristup = (
    $strankaExistuje && $podstrankaExistuje  && $u->maPravo($submenu[$podstranka]['pravo']) ||
    $strankaExistuje && !$podstrankaExistuje && $u->maPravo($menu[$stranka]['pravo'])
  );

  // konstrukce stránky
  if($strankaExistuje && $uzivatelMaPristup)
  {
    $_SESSION['id_admin'] = $u->id(); // součást interface starých modulů
    $_SESSION['id_uzivatele'] = $uPracovni ? $uPracovni->id() : null ; // součást interface starých modulů
    $BEZ_DEKORACE = false;
    $cwd = getcwd(); // uložíme si aktuální working directory pro pozdější návrat
    if($submenu)
    {
      chdir('./scripts/modules/'.$stranka.'/');
      $soubor=$podstranka?$cwd.'/'.$submenu[$podstranka]['soubor']:$cwd.'/'.$submenu[$stranka]['soubor'];
    }
    else
    {
      chdir('./scripts/modules/');
      $soubor=$cwd.'/'.$menu[$stranka]['soubor'];
    }
    ob_start(); // výstup uložíme do bufferu
    require($soubor);

    if(!empty($submenuObject) && $submenuObject->getPatickaSoubor()) {
      require $submenuObject->getPatickaSoubor();
    }

    $vystup = ob_get_clean();
    if($BEZ_DEKORACE) echo $vystup;
    else              $xtpl->assign('obsahRetezec', $vystup);
    chdir($cwd);
    unset($_SESSION['id_uzivatele']);
    unset($_SESSION['id_admin']);
    if($BEZ_DEKORACE) return;
  }
  elseif($strankaExistuje && !$uzivatelMaPristup)
  {
    http_response_code(403);
    $xtpl->parse('all.zakazano');
  }
  else
  {
    http_response_code(404);
    $xtpl->parse('all.nenalezeno');
  }

  // operátor - info & odhlašování
  $xtpl->assign('a', $u->koncA());
  $xtpl->assign('operator', $u->jmenoNick());
  $xtpl->parse('all.operator');
  // výběr uživatele
  if($u->maPravo(100)) // panel úvod - fixme magická konstanta
  {
    if($uPracovni) {
      $xtpl->assign('uPracovni', $uPracovni);
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
  $xtpl->assign('jsVyjimkovac', \Gamecon\Vyjimkovac\Vyjimkovac::js(URL_WEBU.'/ajax-vyjimkovac'));
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();
}
