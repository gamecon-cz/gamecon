<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\XTemplate\XTemplate;

class Menu
{

    protected static $linie;

    protected $stranky = [
        'prihlaska' => 'Přihláška:&ensp;',
        'o-gameconu' => 'Co je GameCon?',
        'organizacni-vypomoc' => 'Organizační výpomoc',
        'chci-se-prihlasit' => 'Chci se přihlásit',
        'prakticke-informace' => 'Praktické informace',
        'celohra' => 'Celohra',
        'kontakty' => 'Kontakty',
        'info-po-gc' => 'Info po GC a zpětná vazba',
        #'https://www.facebook.com/pg/gamecon/photos/?tab=album&album_id=1646393038705358' => 'Fotogalerie',
    ];
    protected $url;

    public function __construct(Uzivatel $u = null, Url $url = null) {
        // personalizace seznamu stránek
        $a = $u ? $u->koncovkaDlePohlavi() : '';
        if (po(REG_GC_OD)) {
            $this->stranky['prihlaska'] .= $u && $u->gcPrihlasen() ?
                '<img src="soubory/styl/ok.png" style="margin-bottom:-3px"> přihlášen' . $a . ' na GC' :
                '<img src="soubory/styl/error.png" style="margin-bottom:-3px"> nepřihlášen' . $a . ' na GC';
        } else {
            $this->stranky['prihlaska'] .= 'přihlašování ještě nezačalo';
        }
        $this->url = $url;
    }

    /** Celý kód menu (html) */
    public function cele() {
        $a = $this->url ? $this->url->cast(0) : null;
        $t = new XTemplate('sablony/menu.xtpl');
        $t->assign('menu', $this);
        if (isset(self::$linie[$a])) {
            $t->assign('aaktiv', 'aktivni');
        }
        if (isset($this->stranky[$a])) {
            $t->assign('saktiv', 'aktivni');
        }
        if ($a === 'blog') {
            $t->assign('baktiv', 'aktivni');
        }
        $t->parse('menu');
        return $t->text('menu');
    }

    /** Seznam linií s prokliky (html) */
    public function linie(): string {
        $linie = self::linieSeznam();
        // ne/zobrazení linku na program
        if (PROGRAM_VIDITELNY && !isset($linie['program'])) {
            $linie = ['program' => 'Program'] + $linie;
        } elseif (!isset($linie['pripravujeme'])) {
            $linie = ['pripravujeme' => 'Letos připravujeme…'] + $linie;
        }
        // výstup
        $o = '';
        foreach ($linie as $a => $l) {
            $o .= "<li><a href=\"$a\">$l</a></li>";
        }
        return $o;
    }

    /** Asoc. pole url linie => název */
    public static function linieSeznam(): array {
        if (!isset(self::$linie)) {
            $typy = TypAktivity::zViditelnych();
            usort($typy, static function ($a, $b) {
                return $a->poradi() - $b->poradi();
            });
            self::$linie = [];
            foreach ($typy as $typ) {
                self::$linie[$typ->url()] = mb_ucfirst($typ->nazev());
            }
        }
        return self::$linie;
    }

    /** Seznam stránek s prokliky (html) */
    public function stranky(): string {
        $o = '';
        foreach ($this->stranky as $a => $l) {
            $o .= "<li><a href=\"$a\">$l</a></li>";
        }
        return $o;
    }

}
