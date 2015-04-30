<?php

class Menu {

  static $linie = array( // TODO cacheování databáze
    'deskovky'  => 'Deskovky a turnaje',
    'rpg'       => 'RPG',
    'larpy'     => 'Larpy',
    'drd'       => 'Mistrovství v DrD',
    'legendy'   => 'Legendy Klubu dobrodruhů',
    'bonusy'    => 'Akční hry a bonusy',
    'epic'      => 'Epické deskovky',
    'prednasky' => 'Přednášky',
    'workshopy' => 'Workshopy',
    'wargaming' => 'Wargaming',
  );

  protected static $stranky = array(
    'o-gameconu'          =>  'O GameConu',
    'chci-se-zapojit'     =>  'Chci se zapojit',
    'chci-se-prihlasit'   =>  'Chci se přihlásit',
    'prakticke-informace' =>  'Praktické informace',
    'forum'               =>  'Fórum',
    'kontakty'            =>  'Kontakty',
    'https://www.facebook.com/media/set/?set=a.846204775390859.1073741832.127768447234499&type=3' => 'Fotogalerie',
  );

  /** Celý kód menu (html) */
  function cele() {
    $a = Url::zAktualni()->cast(0);
    $t = new XTemplate('sablony/menu.xtpl');
    $t->assign('menu', $this);
    if(isset(self::$linie[$a]))     $t->assign('aaktiv', 'aktivni');
    if(isset(self::$stranky[$a]))   $t->assign('saktiv', 'aktivni');
    if($a == 'blog')                $t->assign('baktiv', 'aktivni');
    $t->parse('menu');
    return $t->text('menu');
  }

  /** Seznam linií s prokliky (html) */
  function linie() {
    $o = '';
    foreach(self::$linie as $a => $l) {
      $o .= "<li><a href=\"$a\">$l</a></li>";
    }
    return $o;
  }

  /** Seznam stránek s prokliky (html) */
  function stranky() {
    $o = '';
    foreach(self::$stranky as $a => $l) {
      $o .= "<li><a href=\"$a\">$l</a></li>";
    }
    return $o;
  }

}
