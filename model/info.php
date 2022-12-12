<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Reprezentace metainformací o stránce
 */
class Info
{
    private bool $jsmeNaBete;
    private bool $jsmeNaLocale;

    public function __construct(SystemoveNastaveni $systemoveNastaveni) {
        $this->jsmeNaBete   = $systemoveNastaveni->jsmeNaBete();
        $this->jsmeNaLocale = $systemoveNastaveni->jsmeNaLocale();
    }

    private $nazev;
    private $obrazek;
    private $popis;
    private $site;
    private $titulek;
    private $url;

    function html() {
        $o = '';
        if ($e = $this->titulek()) $o .= '<title>' . $e . '</title>';
        if ($e = $this->nazev()) $o .= '<meta property="og:title" content="' . $e . '">';
        if ($e = $this->url()) $o .= '<meta property="og:url" content="' . $e . '">';
        if ($e = $this->site()) $o .= '<meta property="og:site_name" content="' . $e . '">';
        if ($e = $this->popis()) $o .= '<meta property="og:description" content="' . $e . '">';
        if ($e = $this->obrazek()) {
            if (substr($e, 0, 4) != 'http') $e = URL_WEBU . '/' . $e;
            $o .= '<meta property="og:image" content="' . $e . '">';
        }
        $o .= '<meta property="og:type" content="website">';
        return $o;
    }

    /**
     * @return Info|string|null
     */
    function nazev() {
        if (func_num_args() == 0) {
            return $this->nazev;
        } elseif (func_num_args() == 1) {
            $nazev = func_get_arg(0);
            $prefix  = $this->dejPrefixPodleVyvoje();
            if ($prefix !== '') {
                $nazev = $prefix . ' ' . $nazev;
            }
            $this->nazev = $nazev;
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

    function obrazek() {
        if (func_num_args() == 0) {
            return $this->obrazek;
        } elseif (func_num_args() == 1) {
            $this->obrazek = func_get_arg(0);
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

    function popis() {
        if (func_num_args() == 0) {
            return $this->popis;
        } elseif (func_num_args() == 1) {
            $this->popis = func_get_arg(0);
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

    /** The name of your website (such as IMDb, not imdb.com) */
    function site() {
        if (func_num_args() == 0) {
            return $this->site;
        } elseif (func_num_args() == 1) {
            $this->site = func_get_arg(0);
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

    function titulek() {
        if (func_num_args() == 0) {
            return $this->titulek;
        } elseif (func_num_args() == 1) {
            $titulek = func_get_arg(0);
            $prefix  = $this->dejPrefixPodleVyvoje();
            if ($prefix !== '') {
                $titulek = $prefix . ' ' . $titulek;
            }
            $this->titulek = $titulek;
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

    function dejPrefixPodleVyvoje(): string {
        if ($this->jsmeNaLocale) {
            return 'άλφα';
        }
        if ($this->jsmeNaBete) {
            return 'β';
        }
        return '';
    }

    function url() {
        if (func_num_args() == 0) {
            return $this->url;
        } elseif (func_num_args() == 1) {
            $this->url = func_get_arg(0);
            return $this;
        } else {
            throw new BadMethodCallException();
        }
    }

}
