<?php

use Gamecon\Aktivita\TypAktivity;

class Stranka extends DbObject
{

    protected static $tabulka = 'stranky';
    protected static $pk = 'id_stranky';

    public function html() {
        if (!isset($this->html)) {
            $html = markdownNoCache($this->r['obsah']);
            $html = preg_replace_callback('@(<p>)?\(widget:([a-z\-]+)\)(</p>)?@', function ($m) {
                $w = Widget::zNazvu($m[2]);
                if ($w) {
                    return $w->html();
                }
                return 'widget neexistuje';
            }, $html);
            $this->html = $html;
        }
        return $this->html;
    }

    public function nadpis() {
        $html = preg_quote_wildcard('<h1>~</h1>');
        $md = '^#\s*([^#].+)$';
        preg_match("@$html|$md@m", $this->r['obsah'], $m);
        return @($m[1] ?: $m[2]);
    }

    public function obrazek() {
        $html = preg_quote_wildcard('<img src="~"~>');
        $md = preg_quote_wildcard('![~](~)');
        preg_match("@$html|$md@", $this->r['obsah'], $m);
        return @($m[1] ?: $m[4]);
    }

    public function poradi() {
        return $this->r['poradi'];
    }

    /** Vrátí typ aktivit (linii) pokud stránka patří pod nějakou linii */
    public function typ() {
        if (preg_match('@^([^/]+)/@', $this->r['url_stranky'], $m)) {
            return TypAktivity::zUrl($m[1]);
        }

        return null;
    }

    public function url() {
        return $this->r['url_stranky'];
    }

    static function zUrl($url = null): ?Stranka {
        if (!$url) {
            $url = Url::zAktualni()->cela();
        }
        return self::zWhereRadek('url_stranky = $1', [$url]);
    }

    public static function zUrlPrefixu(string $url): array {
        return self::zWhere('url_stranky LIKE $1', [$url . '/%']);
    }

    static function zVsech(): array {
        return self::zWhere('1 ORDER BY url_stranky');
    }

}
