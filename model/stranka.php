<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Jazyk\Jazyk;

/**
 * For Doctrine entity equivalent @see \App\Entity\Page
 */
class Stranka extends DbObject
{
    /**
     * Ručně psaná anglická úvodní stránka. Ukazuje se na /en/ (web/index.php), dokud není přeložená titulka.
     */
    public const URL_ANGLICKE_TITULKY = 'en';

    protected static $tabulka = 'stranky';
    protected static $pk      = 'id_stranky';
    /** @var array<string, string|null> HTML podle jazyka textu */
    private array $html = [];

    /**
     * @param Jazyk|null $jazyk jazyk textu stránky (ne stránky webu), podle něj markdown volí typografii; výchozí čeština
     */
    public function html(?Jazyk $jazyk = null): ?string
    {
        $jazyk ??= Jazyk::vychozi();
        if (!array_key_exists($jazyk->value, $this->html)) {
            $html       = markdown($this->r['obsah'], $jazyk);
            $html       = preg_replace_callback('@(<p>)?\(widget:([a-z\-]+)\)(</p>)?@', function ($m) {
                $w = Widget::zNazvu($m[2]);
                if ($w) {
                    return $w->html();
                }
                return 'widget neexistuje';
            }, $html);
            // naříklad %PRVNI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%
            $html       = nahradPlaceholderyZaNastaveni($html);
            $this->html[$jazyk->value] = $html;
        }
        return $this->html[$jazyk->value];
    }

    public function nadpis(): string
    {
        $html = preg_quote_wildcard('<h1>~</h1>');
        $md   = '^#\s*([^#].+)$';
        if (!preg_match("@$html|$md@m", $this->r['obsah'], $matches)) {
            return '';
        }
        return $matches[1] ?: $matches[2];
    }

    public function obrazek(): string
    {
        $html = preg_quote_wildcard('<img src="~"~>');
        $md   = preg_quote_wildcard('![~](~)');
        if (!preg_match("@$html|$md@", $this->r['obsah'], $m)) {
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
