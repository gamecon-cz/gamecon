<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Web\Info;

/**
 * Modul stránek (controller). Objektové zapouzdření pro soubory ze složky
 * "moduly" v rootu stránek.
 */
class Modul
{
    private const VYCHOZI = 'titulka';

    protected $src;
    protected $params = [];
    protected $vystup;
    protected $bezDekorace = false;
    protected $bezMenu = false;
    protected $bezStranky = false;
    protected $bezOkraju = false;
    protected $bezPaticky = false;
    protected $blackarrowStyl = false;
    protected $info;
    protected $cssUrls = [];
    protected $jsUrls = [];

    /** @var SystemoveNastaveni */
    private $systemoveNastaveni;

    /** Načte modul ze zadané cesty k souboru */
    protected function __construct(string $soubor, SystemoveNastaveni $systemoveNastaveni) {
        $this->src                = $soubor;
        $this->systemoveNastaveni = $systemoveNastaveni;
    }

    /** Jestli se má modul renderovat bez obalovacího divu (tj. ne jak stránka) */
    protected function bezDekorace($val = null) {
        if (isset($val)) $this->bezDekorace = (bool)$val;
        return $this->bezDekorace;
    }

    /** Jestli se modul má renderovat bez zobrazeného menu */
    public function bezMenu($val = null) {
        if (isset($val)) $this->bezMenu = (bool)$val;
        return $this->bezMenu;
    }

    /** Jestli se má modul renderovat přes celou šířku monitoru */
    public function bezOkraju($val = null) {
        if (isset($val)) $this->bezOkraju = (bool)$val;
        return $this->bezOkraju;
    }

    /** Jestli se má modul renderovat čistě jako plaintext */
    public function bezStranky($val = null) {
        if (isset($val)) $this->bezStranky = $val;
        return $this->bezStranky;
    }

    public function bezPaticky($val = null) {
        if (isset($val)) $this->bezPaticky = $val;
        return $this->bezPaticky;
    }

    /**
     * Jestli je modul v novém vizuálním stylu (codename blackarrow).
     * TODO po zmigrování všech modulů je možné toto postupně odstranit.
     */
    public function blackarrowStyl($val = null) {
        if (isset($val)) $this->blackarrowStyl = $val;
        return $this->blackarrowStyl;
    }

    public function cssUrls() {
        return $this->cssUrls;
    }

    public function info(Info $val = null): ?Info {
        if (isset($val)) $this->info = $val;
        return $this->info;
    }

    public function jsUrls() {
        return $this->jsUrls;
    }

    /** Název modulu (odpovídá části názvu souboru) */
    protected function nazev() {
        return basename($this->src, '.php');
    }

    /** Setter/getter pro parametr (proměnnou) předanou dovnitř modulu */
    public function param($nazev) {
        if (func_num_args() == 2) {
            $this->params[$nazev] = func_get_arg(1);
        }
        return $this->params[$nazev] ?? null;
    }

    public function pridejCssUrl($url) {
        $this->cssUrls[] = $url;
    }

    public function pridejJsSoubor($cesta) {
        $cestaKSouboru  = strpos(realpath($cesta), realpath(WWW)) === 0
            ? $cesta
            : WWW . '/' . $cesta;
        $verze          = md5_file($cestaKSouboru);
        $cestaNaWebu    = ltrim(substr(realpath($cestaKSouboru), strlen(realpath(WWW))), '/');
        $url            = URL_WEBU . '/' . $cestaNaWebu . '?version=' . $verze;
        $this->jsUrls[] = $url;
    }

    /** Vrátí výchozí šablonu pro tento modul (pokud existuje) */
    protected function sablona() {
        $blackarrowSoubor = 'sablony/blackarrow/' . $this->nazev() . '.xtpl';

        if (is_file($blackarrowSoubor)) {
            return new XTemplate($blackarrowSoubor);
        } else {
            return null;
        }
    }

    /**
     * Vykoná kód modulu a nacacheuje výstup pro pozdější použití.
     * Viz, že modul dostává některé parametry pomocí proměnných resp. šablona se
     * načítá automaticky.
     */
    public function spust() {
        extract($this->params); // TODO možná omezit explicitně parametry, které se smí extractnout, ať to není black magic
        $t = $this->sablona();

        ob_start();
        $systemoveNastaveni = $this->systemoveNastaveni;
        $vysledek           = require $this->src;
        $earlyReturn        = ($vysledek === null); // při dokončení skriptu je výsledek 1
        if ($t && !$earlyReturn) {
            $t->parse($this->nazev());
            $t->out($this->nazev());
        }
        $this->vystup = ob_get_clean();

        return $this;
    }

    /** Vrátí výstup, který modul vygeneroval */
    public function vystup() {
        if ($this->bezDekorace || $this->bezStranky)
            return $this->vystup;
        elseif ($this->bezOkraju)
            return $this->vystup . '<style>.hlavni { max-width: 100%; }</style>';
        elseif ($this->blackarrowStyl)
            return '<div>' . $this->vystup . '</div>';
        else
            return '<div class="blok stranka"><div class="obal">' . $this->vystup . '</div></div>';
    }

    /** Načte modul odpovídající dané Url (pokud není zadaná, použije aktuální) */
    public static function zUrl(Url $urlObjekt = null, SystemoveNastaveni $systemoveNastaveni) {
        $url        = null;
        $podstranka = null;
        if (!$urlObjekt) {
            $urlObjekt  = Url::zAktualni();
            $url        = $urlObjekt->cast(0);
            $podstranka = $urlObjekt->cast(1);
        }
        if (!$url) {
            $url = self::VYCHOZI;
        }
        return self::zNazvu($url, $podstranka, $systemoveNastaveni);
    }

    /** Načte modul podle daného názvu */
    public static function zNazvu(
        ?string            $nazev,
        string             $podstranka = null,
        SystemoveNastaveni $systemoveNastaveni
    ): ?self {
        if ($nazev) {
            if ($podstranka) {
                $pripona = str_ends_with($podstranka, '.php')
                    ? ''
                    : '.php';
                $soubor  = "moduly/{$nazev}/{$podstranka}{$pripona}";
                if (is_file($soubor)) {
                    return new self($soubor, $systemoveNastaveni);
                }
            }
            $soubor = "moduly/{$nazev}.php";
            if (is_file($soubor)) {
                return new self($soubor, $systemoveNastaveni);
            }
            $soubor = "moduly/{$nazev}/{$nazev}.php";
            if (is_file($soubor)) {
                return new self($soubor, $systemoveNastaveni);
            }
        }
        return null;
    }

}
