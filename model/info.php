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

    private ?string $nazev = null;
    private ?string $obrazek = null;
    private ?string $popis = null;
    private ?string $site = null;
    private ?string $titulek = null;
    private ?string $url = null;

    public function html(): string {
        $o = '';
        if ($titulek = $this->titulek()) {
            $o .= '<title>' . $titulek . '</title>';
        }
        if ($titulek = $this->nazev()) {
            $o .= '<meta property="og:title" content="' . $titulek . '">';
        }
        if ($titulek = $this->url()) {
            $o .= '<meta property="og:url" content="' . $titulek . '">';
        }
        if ($titulek = $this->site()) {
            $o .= '<meta property="og:site_name" content="' . $titulek . '">';
        }
        if ($titulek = $this->popis()) {
            $o .= '<meta property="og:description" content="' . $titulek . '">';
        }
        if ($titulek = $this->obrazek()) {
            if (substr($titulek, 0, 4) !== 'http') $titulek = URL_WEBU . '/' . $titulek;
            $o .= '<meta property="og:image" content="' . $titulek . '">';
        }
        $o .= '<meta property="og:type" content="website">';
        return $o;
    }

    public function nazev(string $nazev = null): string|null|Info {
        if ($nazev === null) {
            return $this->nazev;
        }
        $this->nazev = $nazev;
        return $this;
    }

    public function obrazek(string $obrazek = null): string|null|Info {
        if ($obrazek === null) {
            return $this->obrazek;
        }
        $this->obrazek = $obrazek;
        return $this;
    }

    public function popis(string $popis = null): string|null|Info {
        if ($popis === null) {
            return $this->popis;
        }
        $this->popis = $popis;
        return $this;
    }

    /** The name of your website (such as IMDb, not imdb.com) */
    public function site(string $site = null): string|null|Info {
        if ($site === null) {
            return $this->site;
        }
        $this->site = $site;
        return $this;
    }

    public function titulek(string $titulek = null): string|null|Info {
        if ($titulek === null) {
            return $this->titulek;
        }
        if ($this->jsmeNaLocale) {
            $titulek = 'άλφα ' . $titulek;
        } elseif ($this->jsmeNaBete) {
            $titulek = 'β ' . $titulek;
        }
        $this->titulek = $titulek;
        return $this;
    }

    public function url(string $url = null): string|null|Info {
        if ($url === null) {
            return $this->url;
        }
        $this->url = $url;
        return $this;
    }

}
