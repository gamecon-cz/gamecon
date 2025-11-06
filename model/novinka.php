<?php

use \Gamecon\Cas\DateTimeCz;

/**
 * For Doctrine entity equivalent @see \App\Entity\News
 */
class Novinka extends DbObject
{

    protected static $tabulka = 'novinky';
    protected static $prvniObrazek = '@<img src="([^"]+)"[^>]*>@'; // RV odpovídající prvnímu obrázku v textu
    private ?DateTimeCz $vydat = null;

    const NOVINKA = 1;
    const BLOG = 2;

    function autor()
    {
        return preg_replace('@"(\S+)"@', '„$1“', $this->r['autor']);
    }

    function datum()
    {
        return date('j.n.', strtotime($this->r['vydat']));
    }

    function hlavniText()
    {
        return preg_replace(self::$prvniObrazek, '', $this->text(), 1);
    }

    /** Prvních $n znaků příspěvku */
    function nahled($n = 250)
    {
        $sub = mb_substr(strip_tags($this->text()), 0, $n);
        if (isset($sub[0]) && $sub[0] == '_') $sub[0] = ' ';
        return $sub;
    }

    function nazev()
    {
        return $this->r['nazev'];
    }

    /** url obrázku příspěvku */
    function obrazek()
    {
        preg_match(self::$prvniObrazek, $this->text(), $m);
        return @$m[1]; // TODO odstranit
    }

    function text()
    {
        return markdown($this->r['text']);
    }

    function typ()
    {
        return $this->r['typ'];
    }

    function typSlovy(): string
    {
        $typ = (int)($this->r['typ'] ?? null);   // ošetření i případného null / neexistence
        $typy = [
            self::BLOG    => 'blog',
            self::NOVINKA => 'novinka',
        ];

        return $typy[$typ] ?? 'neznámý typ';     // fallback místo „Undefined array key“
    }


    /** název enkódovaný do url formátu */
    function url()
    {
        return $this->r['url'];
    }

    function vydat()
    {
        if (empty($this->vydat)) {
            $this->vydat = new DateTimeCz($this->r['vydat']);
        }
        return $this->vydat;
    }

    static function zNejnovejsi($typ = self::NOVINKA)
    {
        return self::zWhereRadek('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC LIMIT 1', [$typ]);
    }

    static function zNejnovejsich($start = 0, $limit = 20)
    {
        return self::zWhere('vydat <= NOW() ORDER BY vydat DESC LIMIT $1, $2', [$start, $limit]);
    }

    static function zTypu($typ)
    {
        return self::zWhere('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC', [$typ]);
    }

    static function zUrl($url, $typ = self::NOVINKA)
    {
        return self::zWhereRadek('url = $1 AND typ = $2', [$url, $typ]);
    }

    static function zVsech(bool $zCache = false): array
    {
        return self::zWhere('1 ORDER BY vydat = 0 DESC, vydat DESC');
    }
}
