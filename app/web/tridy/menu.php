<?php

class Menu {

  static $linie = array( // TODO cacheování databáze
    'deskovky'  => 'Deskovky',
    'rpg'       => 'RPG',
    'larpy'     => 'Larpy',
    'drd'       => 'Mistrovství v DrD',
    'legendy'   => 'Legendy klubu dobrodruhů',
    'bonusy'    => 'Trojboj a akční hry',
    'prednasky' => 'Přednášky',
    'workshopy' => 'Workshopy',
    'wargaming' => 'Wargaming',
  );

  protected static $stranky = array(
    'prihlasovani-na-gc'  => 'Přihlašování na GC',
    'na-gameconu'     => 'Průvodce GameConem',
    'organizatori'    => 'Kontakty',
    'napsali-o-nas'   => 'Media',
    'dalsi-partneri'  => 'Partneři a zajímavé odkazy',
    ''                => 'Titulka',
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
