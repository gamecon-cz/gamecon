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

// Magické přihlášení z administračního rozcestníku na ostré (?gcsso= token).
// Varianta pro nejstarší éru (2012-2013): login se řeší přímo tady v admin/index.php
// (žádné samostatné prihlaseni.php), get() vrací '' (ne null), Dev třídy žijí v
// sdilene/Dev/ a vendor/autoload není → require_once. Pravidla drží
// ArchivSsoPrihlaseni (volá rovnou Uzivatel::prihlasId — dbOneCol téhle éry nebere
// pole parametrů). Mint je na ostré v admin/.../web/stare-rocniky.php.
if(get('gcsso') !== '') {
  $gcsso = get('gcsso');
  require_once(__DIR__ . '/../sdilene/Dev/OvereneSso.php');
  require_once(__DIR__ . '/../sdilene/Dev/CrossSiteLogin.php');
  require_once(__DIR__ . '/../sdilene/Dev/SsoParovaciCookie.php');
  require_once(__DIR__ . '/../sdilene/Dev/ArchivSsoPrihlaseni.php');

  // GAMECON_SSO_KEY = klíč odvozený pro TENTO ročník (HMAC(rok, master)), vstříknutý
  // deployem přes -e. Prázdný → SSO se neuplatní.
  $ssoKlic = defined('GAMECON_SSO_KEY') ? GAMECON_SSO_KEY : '';
  $ssoPrihlaseni = new Gamecon\Dev\ArchivSsoPrihlaseni($ssoKlic);
  $uSso = $ssoPrihlaseni->prihlas(
    (string) $gcsso,
    Gamecon\Dev\SsoParovaciCookie::precti(),
    $u
  );
  if($uSso !== null)
    $u = $uSso;

  // Token z URL odstraníme (ať nezůstane v historii / referreru).
  $cistaQuery = $_GET;
  unset($cistaQuery['gcsso']);
  $cilo = strtok($_SERVER['REQUEST_URI'], '?');
  if(!empty($cistaQuery))
    $cilo .= '?' . http_build_query($cistaQuery);
  back($cilo);
}
if(post('odhlasNAdm'))
  $u->odhlas(true);

//staré zpracování výběru uživatele pro admin. práci rozšířené o OOP
$uPracovni=null;
require_once('./scripts/vyber-uzivatele-old.hhp');

//xtemplate inicializace
$xtpl=new XTemplate('./templates/main.xtpl');
$xtpl->assign('pageTitle','GameCon – Administrace');

if(!get('req'))
  back(URL_ADMIN.'/uvod'); //nastavení stránky, prázdná url => přesměrování na úvod
  // Absolutní URL vč. /admin: relativní '/uvod' míří na veřejný web (500), ne do adminu.
$req=explode('/',get('req'));
$stranka=$req[0];
$podstranka=isset($req[1])?$req[1]:'';
//zobrazení stránky
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
  //konstrukce menu
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
  //konstrukce submenu
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
  //konstrukce stránky
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
  $xtpl->parse('all.odhlasovani');
  $xtpl->parse('all');
  $xtpl->out('all');
  profilInfo();  
}

?>
