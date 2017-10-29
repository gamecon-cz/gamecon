<?php

class Menu {

  protected static $linie;

  protected $stranky = [
    'prihlaska'           =>  'Přihláška:&ensp;',
    'o-gameconu'          =>  'Co je GameCon?',
    'o-parconu'           =>  'Co je ParCon?',
    'organizacni-vypomoc'     =>  'Organizační výpomoc',
    'chci-se-prihlasit'   =>  'Chci se přihlásit',
    'en'                  =>  'English program',
    'prakticke-informace' =>  'Praktické informace',
    'kontakty'            =>  'Kontakty',
    'https://www.facebook.com/pg/gamecon/photos/?tab=album&album_id=1646393038705358' => 'Fotogalerie',
  ];
  protected $url;

  function __construct(Uzivatel $u = null, Url $url = null) {
    // personalizace seznamu stránek
    $a = $u ? $u->koncA() : '';
    if(po(REG_GC_OD)) {
      $this->stranky['prihlaska'] .= $u && $u->gcPrihlasen() ?
        '<img src="soubory/styl/ok.png" style="margin-bottom:-3px"> přihlášen'.$a.' na GC':
        '<img src="soubory/styl/error.png" style="margin-bottom:-3px"> nepřihlášen'.$a.' na GC';
    } else {
      $this->stranky['prihlaska'] .= 'přihlašování ještě nezačalo';
    }
    $this->url = $url;
  }

  /** Celý kód menu (html) */
  function cele() {
    $a = $this->url ? $this->url->cast(0) : null;
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
    $linie = self::linieSeznam();    
    $u = Uzivatel::zSession();
    
    // ne/zobrazení linku na program
    if(PROGRAM_VIDITELNY && !isset($linie['program']))  {      
      if(isset($u)){
        $linie = ['muj-program' => 'Můj program'] + $linie;
      }
      $linie = ['program' => 'Program'] + $linie;
    }elseif(!isset($linie['pripravujeme']))              $linie = ['pripravujeme' => 'Letos připravujeme…'] + $linie;
    // výstup
    $o = '';
    foreach($linie as $a => $l) {
      $o .= "<li><a href=\"$a\">$l</a></li>";
    }
    return $o;
  }

  /** Asoc. pole url linie => název */
  static function linieSeznam() {
    if(!isset(self::$linie)) { // TODO cacheování
      $typy = Typ::zViditelnych();
      usort($typy, function($a, $b) { return $a->poradi() - $b->poradi(); });
      foreach($typy as $typ) {
        self::$linie[$typ->url()] = mb_ucfirst($typ->nazev());
      }
    }
    return self::$linie;
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
