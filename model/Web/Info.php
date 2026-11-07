<?php

namespace Gamecon\Web;

use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Reprezentace metainformací o stránce
 */
class Info
{
    private Prostredi $prostredi;

    public function __construct(SystemoveNastaveni $systemoveNastaveni)
    {
        $this->prostredi = $systemoveNastaveni->prostredi();
    }

    private $nazev;
    private $obrazek;
    private $popis;
    private $site;
    private $titulek;
    private $url;
    /** @var array<string, string|int> */
    private array $kanonickyDotaz = [];

    public function html(): string
    {
        $o = [];
        if ($e = $this->titulek()) {
            $o[] = '<title>' . $e . '</title>';
        }
        if ($e = $this->nazev()) {
            $o[] = '<meta property="og:title" content="' . $e . '">';
        }
        if ($e = $this->getKanonickaUrl()) {
            $e   = htmlspecialchars($e);
            $o[] = '<meta property="og:url" content="' . $e . '">';
            $o[] = '<link rel="canonical" href="' . $e . '">';
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
        return $this->prostredi->prefix();
    }

    /**
     * Canonical address of the page without query. It is printed as both `og:url` and `rel="canonical"`.
     */
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

    /**
     * A query parameter that selects different content (a past year, a page of a list), so the canonical address keeps it.
     * Any other parameter (tracking, language switch) is not part of the canonical address.
     */
    public function setKanonickyParametr(string $nazev, string|int $hodnota): static
    {
        $this->kanonickyDotaz[$nazev] = $hodnota;

        return $this;
    }

    public function getKanonickaUrl(): ?string
    {
        if (! $this->url) {
            return null;
        }

        return $this->kanonickyDotaz === []
            ? $this->url
            : $this->url . '?' . http_build_query($this->kanonickyDotaz);
    }

    /**
     * One address per page: no trailing slash (the home page keeps the single one), whatever way it was requested.
     */
    public static function getKanonickaUrlZCesty(string $zakladniUrl, string $cesta): string
    {
        return rtrim($zakladniUrl, '/') . '/' . trim($cesta, '/');
    }

    public function htmlTotoSeUkazujePouzeNaTestu(string $duvod = ''): string
    {
        $duvod = $duvod !== ''
            ? "<br>$duvod"
            : '';
        return $this->prostredi !== Prostredi::Production
            ? '<span class="hinted">🙋<span class="hint"><em>(toto se ukazuje pouze na testu)</em>' . $duvod . ' </span></span>'
            : '';
    }
}
