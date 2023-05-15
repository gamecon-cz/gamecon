<?php

/**
 * Náhled / prohlížeč obrázku s cacheováním atd..
 */

class Nahled
{

    protected $s       = null;
    protected $v       = null;
    protected $mod     = null;
    protected $soubor;
    protected $datum;         // poslední změna orig. souboru
    protected $kvalita = 92;  // kvalita exportu

    const PASUJ       = 1;
    const POKRYJ      = 2;
    const POKRYJ_OREZ = 3;

    protected function __construct($soubor)
    {
        $this->soubor = $soubor;
        $this->datum  = @filemtime($this->soubor);
        if ($this->datum === false) {
            throw new RuntimeException('Obrázek neexistuje. Hledán na ' . $soubor);
        }
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    function __toString()
    {
        try {
            return $this->url();
        } catch (Exception $e) {
            return ''; // __toString nesmí vyhazovat výjimky
        }
    }

    /** Nastaví kvalitu jpeg exportu */
    function kvalita($q)
    {
        $this->kvalita = $q;
        return $this;
    }

    protected function mod($s, $v, $mod): Nahled
    {
        $this->mod = $mod;
        $this->s   = $s;
        $this->v   = $v;
        return $this;
    }

    /** Zmenší obrázek aby pasoval do obdelníku s šířkou $s a výškou $v */
    function pasuj($s, $v = null): Nahled
    {
        return $this->mod($s, $v, self::PASUJ);
    }

    /** Zmenší proporčně obrázek aby šířka byla min $s a výška min $v */
    function pokryj($s, $v)
    {
        return $this->mod($s, $v, self::POKRYJ);
    }

    /** Zmenší obrázek aby pokrýval šířku i výšku, vystředí a ořízne přebytek */
    function pokryjOrez($s, $v)
    {
        return $this->mod($s, $v, self::POKRYJ_OREZ);
    }

    /** Uloží stávající soubor s požadovanými úpravami */
    protected function uloz($cil)
    {
        $o = Obrazek::zSouboru($this->soubor);
        $s = $this->s ?: 10000;
        $v = $this->v ?: 10000;
        if ($this->mod == self::PASUJ) $o->fitCrop($s, $v); // pozor, cache může odstínit změny v tomto kódu
        if ($this->mod == self::POKRYJ) $o->fillCrop($s, $v);
        if ($this->mod == self::POKRYJ_OREZ) $o->fill($s, $v);
        $o->uloz($cil, $this->kvalita);
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    function url()
    {
        $hash  = md5($this->soubor . $this->mod . $this->v . $this->s . $this->kvalita);
        $cache = CACHE . '/img/' . $hash . '.jpg';
        $url   = URL_CACHE . '/img/' . $hash . '.jpg?m=' . $this->datum;
        if (@filemtime($cache) < $this->datum) {
            pripravCache(CACHE . '/img/');
            $this->uloz($cache);
        }
        return $url;
    }

    static function zSouboru($nazev)
    {
        return new self($nazev);
    }

}
