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

  protected $stranky = array(
    'prihlaska'           =>  'Přihláška:&ensp;',
    'o-gameconu'          =>  'O GameConu',
    'chci-se-zapojit'     =>  'Chci se zapojit',
    'chci-se-prihlasit'   =>  'Chci se přihlásit',
    'prakticke-informace' =>  'Praktické informace',
    'forum'               =>  'Fórum',
    'kontakty'            =>  'Kontakty',
    'https://www.facebook.com/media/set/?set=a.846204775390859.1073741832.127768447234499&type=3' => 'Fotogalerie',
  );

  function __construct(Uzivatel $u = null) {
    // personalizace seznamu stránek
    $a = $u ? $u->koncA() : '';
    $this->stranky['prihlaska'] .= $u && $u->gcPrihlasen() ?
      '<img src="soubory/styl/ok.png" style="margin-bottom:-3px"> přihlášen'.$a.' na GC':
      '<img src="soubory/styl/error.png" style="margin-bottom:-3px"> nepřihlášen'.$a.' na GC';
  }

  /** Celý kód menu (html) */
  function cele() {
    $a = Url::zAktualni()->cast(0);
    $t = new XTemplate('sablony/menu.xtpl');
    $t->assign('menu', $this);
    if(isset(self::$linie[$a]))     $t->assign('aaktiv', 'aktivni');
    if(isset($this->stranky[$a]))   $t->assign('saktiv', 'aktivni');
    if($a == 'blog')                $t->assign('baktiv', 'aktivni');
    $t->parse('menu');
    return $t->text('menu');
  }

  /** Seznam linií s prokliky (html) */
  function linie() {
    $linie = self::$linie;
    // ne/zobrazení linku na program
    if(PROGRAM_VIDITELNY && !isset(self::$linie['program']))  $linie = ['program' => 'Program'] + $linie;
    elseif(!isset(self::$linie['pripravujeme']))              $linie = ['pripravujeme' => 'Letos připravujeme…'] + $linie;
    // výstup
    $o = '';
    foreach($linie as $a => $l) {
      $o .= "<li><a href=\"$a\">$l</a></li>";
    }
    return $o;
  }

  /** Seznam stránek s prokliky (html) */
  function stranky() {
    $o = '';
    foreach($this->stranky as $a => $l) {
      $o .= "<li><a href=\"$a\">$l</a></li>";
    }
    return $o;
  }

}
