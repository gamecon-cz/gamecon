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
            $o[] = '<title>' . self::escapuj($e) . '</title>';
        }
        if ($e = $this->nazev()) {
            $o[] = '<meta property="og:title" content="' . self::escapuj($e) . '">';
        }
        if ($e = $this->getKanonickaUrl()) {
            $e   = htmlspecialchars($e);
            $o[] = '<meta property="og:url" content="' . $e . '">';
            $o[] = '<link rel="canonical" href="' . $e . '">';
        }
        if ($e = $this->site()) {
            $o[] = '<meta property="og:site_name" content="' . self::escapuj($e) . '">';
        }
        if ($e = $this->popis()) {
            $o[] = '<meta property="og:description" content="' . self::escapuj($e) . '">';
        }
        if ($e = $this->obrazek()) {
            $e = $this->obrazekProSocialniSite((string) $e);
            if (substr($e, 0, 4) != 'http') $e = URL_WEBU . '/' . $e;
            $o[] = '<meta property="og:image" content="' . self::escapuj($e) . '">';
        }
        $o[] = '<meta property="og:type" content="website">';

        return implode("\n", $o);
    }

    /**
     * Název stránky bývá text, který napsal uživatel (jméno vypravěče na /<id>), a ten nesmí ukončit <title> ani atribut.
     * Nadpis stránky z CMS (`Stranka::nadpis()`) a obrázek z jejího HTML ale entity už mají (`&amp;`), proto se nezdvojují.
     */
    private static function escapuj(string $text): string
    {
        return htmlspecialchars($text, double_encode: false);
    }

    /**
     * Náhled odkazu na sociální síti nebo v chatu čte jejich robot, a ten AVIF nezobrazí (nebo se to nedá zaručit),
     * proto se obrázek ve formátu AVIF z našeho webu nahradí JPEG téhož obrázku. Cokoli jiného zůstane, jak je.
     */
    private function obrazekProSocialniSite(string $obrazek): string
    {
        $cesta = rawurldecode((string) parse_url($obrazek, PHP_URL_PATH));
        if (!preg_match('~\.avif$~i', $cesta)) {
            return $obrazek;
        }
        if (str_starts_with($obrazek, 'http')) {
            $zakladniUrl = rtrim(URL_WEBU, '/') . '/';
            if (!str_starts_with($obrazek, $zakladniUrl)) {
                return $obrazek;
            }
            $cesta = rawurldecode((string) parse_url(substr($obrazek, strlen($zakladniUrl)), PHP_URL_PATH));
        }
        $soubor = rtrim(WWW, '/') . '/' . ltrim($cesta, '/');
        if (str_contains($cesta, '..') || !is_file($soubor)) {
            return $obrazek;
        }
        try {
            // stejný obrázek v JPEG, bez ořezu a zvětšení, jen s omezenou délkou strany: robot sociální sítě větší nenačte
            return \Nahled::zeSouboru($soubor)->kvalita(OBRAZEK_WEBP_KVALITA)->omez(1200)->jakoJpeg()->url();
        } catch (\Exception) {
            // soubor, který ImageMagick nepřečte, nebo nezapisovatelná cache ať neshodí celou hlavičku stránky
            return $obrazek;
        }
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
