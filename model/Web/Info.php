<?php

namespace Gamecon\Web;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Reprezentace metainformací o stránce
 */
class Info
{
    private bool $jsmeNaBete;
    private bool $jsmeNaLocale;

    public function __construct(SystemoveNastaveni $systemoveNastaveni)
    {
        $this->jsmeNaBete   = $systemoveNastaveni->jsmeNaBete();
        $this->jsmeNaLocale = $systemoveNastaveni->jsmeNaLocale();
    }

    private $nazev;
    private $obrazek;
    private $popis;
    private $site;
    private $titulek;
    private $url;

    public function html(): string
    {
        $o = [];
        if ($e = $this->titulek()) {
            $o[] = '<title>' . $e . '</title>';
        }
        if ($e = $this->nazev()) {
            $o[] = '<meta property="og:title" content="' . $e . '">';
        }
        if ($e = $this->url()) {
            $o[] = '<meta property="og:url" content="' . $e . '">';
        }
        if ($e = $this->site()) {
            $o[] = '<meta property="og:site_name" content="' . $e . '">';
        }
        if ($e = $this->popis()) {
            $o[] = '<meta property="og:description" content="' . $e . '">';
        }
        if ($e = $this->obrazek()) {
            if (substr($e, 0, 4) != 'http') $e = URL_WEBU . '/' . $e;
            $o[] = '<meta property="og:image" content="' . $e . '">';
        }
        $o[] = '<meta property="og:type" content="website">';

        return implode("\n", $o);
    }

    /**
     * @return Info|string|null
     */
    public function nazev(string ...$nazev)
    {
        if ($nazev === []) {
            return $this->nazev;
        }
        $nazev       = array_filter($nazev, static fn(string $castNazvu) => $castNazvu !== '');
        $nazev       = array_unique($nazev);
        $nazev       = implode(' – ', $nazev);
        $nazev       = $this->pridejPrefixPodleVyvoje($nazev);
        $this->nazev = $nazev;
        return $this;
    }

    public function obrazek()
    {
        if (func_num_args() == 0) {
            return $this->obrazek;
        } else if (func_num_args() == 1) {
            $this->obrazek = func_get_arg(0);
            return $this;
        } else {
            throw new \BadMethodCallException();
        }
    }

    public function popis()
    {
        if (func_num_args() == 0) {
            return $this->popis;
        } else if (func_num_args() == 1) {
            $this->popis = func_get_arg(0);
            return $this;
        } else {
            throw new \BadMethodCallException();
        }
    }

    /** The name of your website (such as IMDb, not imdb.com) */
    public function site()
    {
        if (func_num_args() == 0) {
            return $this->site;
        } else if (func_num_args() == 1) {
            $this->site = func_get_arg(0);
            return $this;
        } else {
            throw new \BadMethodCallException();
        }
    }

    public function titulek()
    {
        return $this->vytvorTitulek();
    }

    private function vytvorTitulek()
    {
        if (!$this->nazev()) {
            $this->nazev('Gamecon');
        }
        return $this->nazev();
    }

    public function pridejPrefixPodleVyvoje(string $nazev): string
    {
        $prefix = $this->prefixPodleVyvoje();
        if ($prefix !== '' && !str_starts_with($nazev, $prefix)) {
            $nazev = $prefix . ' ' . $nazev;
        }
        return $nazev;
    }

    private function prefixPodleVyvoje(): string
    {
        if ($this->jsmeNaLocale) {
            return 'άλφα';
        }
        if ($this->jsmeNaBete) {
            return 'β';
        }
        return '';
    }

    public function url()
    {
        if (func_num_args() == 0) {
            return $this->url;
        } else if (func_num_args() == 1) {
            $this->url = func_get_arg(0);
            return $this;
        } else {
            throw new \BadMethodCallException();
        }
    }

    public function htmlTotoSeUkazujePouzeNaTestu(string $duvod = ''): string
    {
        $duvod = $duvod !== ''
            ? "<br>$duvod"
            : '';
        return $this->jsmeNaLocale || $this->jsmeNaBete
            ? '<span class="hinted">🙋<span class="hint"><em>(toto se ukazuje pouze na testu)</em>' . $duvod . ' </span></span>'
            : '';
    }
}
