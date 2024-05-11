<?php

/**
 * Náhled / prohlížeč obrázku s cacheováním atd..
 */

class Nahled
{

    protected $s       = null;
    protected $v       = null;
    protected $mod     = null;
    protected string $soubor;
    protected ?int $datum;         // poslední změna orig. souboru
    protected $kvalita = 92;  // kvalita exportu

    const PASUJ       = 1;
    const POKRYJ      = 2;
    const POKRYJ_OREZ = 3;

    protected function __construct(string $soubor)
    {
        $this->soubor = $soubor;
        $this->datum  = @filemtime($this->soubor) ?: null;
        if (!$this->datum) {
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
    protected function uloz(string $cil)
    {
        $o = Obrazek::zSouboru($this->soubor);
        $s = $this->s ?: 10000;
        $v = $this->v ?: 10000;
        switch ($this->mod) {
            case self::PASUJ:
                $o->fitCrop($s, $v);
                break;
            case self::POKRYJ:
                $o->fillCrop($s, $v);
                break;
            case self::POKRYJ_OREZ:
                $o->fill($s, $v);
                break;
        }
        $o->uloz($cil, $this->kvalita);
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    function url(): string
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

    static function zeSouboru(string $nazev): self
    {
        return new self($nazev);
    }

}
