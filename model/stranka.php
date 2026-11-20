<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;

/**
 * Stránka z CMS. Obsah je česky (`obsah`) a volitelně anglicky (`obsah_en`): anglický web ukáže anglický, když ho stránka
 * má, jinak český (@see getJazykObsahu()).
 *
 * For Doctrine entity equivalent @see \App\Entity\Page
 */
class Stranka extends DbObject
{
    /**
     * Stará ručně psaná anglická úvodní stránka z doby, kdy titulka nebyla přeložená. Její adresa přesměruje na /en/
     * (web/index.php) a do sitemapy nepatří.
     */
    public const URL_ANGLICKE_TITULKY = 'en';

    protected static $tabulka = 'stranky';
    protected static $pk      = 'id_stranky';
    /** @var array<string, string|null> HTML podle jazyka obsahu */
    private array $html = [];

    /**
     * Stránka má anglický obsah. Prázdný (NULL i jen mezery, což ukládá formulář adminu) znamená, že je jen česky.
     */
    public function isPrelozena(): bool
    {
        return trim((string) ($this->r['obsah_en'] ?? '')) !== '';
    }

    /**
     * Jazyk, ve kterém se obsah ukáže na stránce webu v jazyce $jazykStranky (výchozí je jazyk požadavku):
     * ten jazyk, když v něm stránka obsah má, jinak čeština.
     */
    public function getJazykObsahu(?Jazyk $jazykStranky = null): Jazyk
    {
        return ($jazykStranky ?? AktualniJazyk::jazyk()) === Jazyk::ANGLICTINA && $this->isPrelozena()
            ? Jazyk::ANGLICTINA
            : Jazyk::vychozi();
    }

    /**
     * Markdown obsahu v jazyce stránky webu, bez překladu český. @see getJazykObsahu()
     */
    public function getObsah(?Jazyk $jazykStranky = null): string
    {
        return match ($this->getJazykObsahu($jazykStranky)) {
            Jazyk::CESTINA    => (string) $this->r['obsah'],
            Jazyk::ANGLICTINA => (string) $this->r['obsah_en'],
        };
    }

    /**
     * HTML obsahu v jazyce stránky webu (výchozí je jazyk požadavku), bez překladu české. Typografie a data
     * v zástupných značkách (`%KLIC|datum:FORMAT_…%`) jsou podle jazyka obsahu, ne stránky.
     */
    public function html(?Jazyk $jazykStranky = null): ?string
    {
        $jazykObsahu = $this->getJazykObsahu($jazykStranky);
        if (!array_key_exists($jazykObsahu->value, $this->html)) {
            $html       = markdown($this->getObsah($jazykObsahu), $jazykObsahu);
            $html       = preg_replace_callback('@(<p>)?\(widget:([a-z\-]+)\)(</p>)?@', function ($m) {
                $w = Widget::zNazvu($m[2]);
                if ($w) {
                    return $w->html();
                }
                return 'widget neexistuje';
            }, $html);
            // naříklad %PRVNI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%
            $html       = nahradPlaceholderyZaNastaveni($html, $jazykObsahu);
            $this->html[$jazykObsahu->value] = $html;
        }
        return $this->html[$jazykObsahu->value];
    }

    /**
     * Nadpis z obsahu v jazyce stránky webu (výchozí je jazyk požadavku), bez překladu z českého.
     */
    public function nadpis(?Jazyk $jazykStranky = null): string
    {
        $html = preg_quote_wildcard('<h1>~</h1>');
        $md   = '^#\s*([^#].+)$';
        if (!preg_match("@$html|$md@m", $this->getObsah($jazykStranky), $matches)) {
            return '';
        }
        return $matches[1] ?: $matches[2];
    }

    /**
     * První obrázek z obsahu v jazyce stránky webu (výchozí je jazyk požadavku), bez překladu z českého.
     */
    public function obrazek(?Jazyk $jazykStranky = null): string
    {
        $html = preg_quote_wildcard('<img src="~"~>');
        $md   = preg_quote_wildcard('![~](~)');
        if (!preg_match("@$html|$md@", $this->getObsah($jazykStranky), $m)) {
            return '';
        }
        return $m[1] ?: $m[4];
    }

    public function poradi()
    {
        return $this->r['poradi'];
    }

    public function isRedirect(): bool
    {
        return boolval($this->r['redirect']);
    }

    public function redirectUrl(): string
    {
        return $this->r['obsah'];
    }

    /** Vrátí typ aktivit (linii) pokud stránka patří pod nějakou linii */
    public function typ()
    {
        if (preg_match('@^([^/]+)/@', $this->r['url_stranky'], $m)) {
            return TypAktivity::zUrl($m[1]);
        }

        return null;
    }

    public function url()
    {
        return $this->r['url_stranky'];
    }

    /** Absolutní URL na veřejném webu */
    public function urlNaWebu(): string
    {
        return URL_WEBU . '/' . $this->url();
    }

    static function zUrl($url = null): ?Stranka
    {
        if (!$url) {
            $url = Url::zAktualni()->cela();
        }
        return self::zWhereRadek('url_stranky = $1', [$url]);
    }

    public static function zUrlPrefixu(string $url): array
    {
        return self::zWhere('url_stranky LIKE $1', [$url . '/%']);
    }

    static function zVsech(bool $zCache = false): array
    {
        return self::zWhere('1 ORDER BY url_stranky');
    }

}
