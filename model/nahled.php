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
        if (!file_exists($soubor) || !is_readable($soubor)) {
            throw new RuntimeException('Obrázek neexistuje nebo není čitelný. Hledán na ' . $soubor);
        }
        $this->soubor = $soubor;
        $this->datum  = @filemtime($this->soubor) ?: null;
        if (!$this->datum) {
            throw new RuntimeException('Nepodařilo se zjistit datum modifikace obrázku: ' . $soubor);
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
        $this->kvalita = (int) $q;
        return $this;
    }

    protected function mod($s, $v, $mod): Nahled
    {
        $this->mod = $mod;
        $this->s   = $s ? (int)$s : null;
        $this->v   = $v ? (int)$v : null;
        return $this;
    }

    /** Zmenší obrázek aby pasoval do obdelníku s šířkou $s a výškou $v */
    function pasuj($s, $v = null): Nahled
    {
        // make sure the image is not made larger
        if ($this->s < $s && $this->v < $v) {
            return $this;
        }
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

    /** Uloží stávající soubor s požadovanými úpravami do WebP formátu */
    protected function uloz(string $cil)
    {
        try {
            $imagick = new Imagick($this->soubor);

            $s = $this->s ?: $imagick->getImageWidth();
            $v = $this->v ?: $imagick->getImageHeight();
            
            $s = max(1, (int)$s);
            $v = max(1, (int)$v);

            if ($this->mod) {
                switch ($this->mod) {
                    case self::PASUJ:
                        $imagick->thumbnailImage($s, $v, true, false);
                        break;
                    case self::POKRYJ:
                        $imagick->thumbnailImage($s, $v, true, true);
                        break;
                    case self::POKRYJ_OREZ:
                        $imagick->cropThumbnailImage($s, $v);
                        break;
                }
            }

            $imagick->setImageFormat('WEBP');
            $imagick->setImageCompressionQuality($this->kvalita);
            
            $imagick->writeImage($cil);
            $imagick->clear();
            $imagick->destroy();
        } catch (ImagickException $e) {
            throw new RuntimeException("Chyba při zpracování obrázku (Imagick): " . $e->getMessage(), 0, $e);
        }
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    function url(): string
    {
        $hash  = md5($this->soubor . $this->mod . $this->v . $this->s . $this->kvalita . 'v2_webp'); // Added version/format to hash
        $cache = CACHE . '/img/' . $hash . '.webp'; // Changed extension to .webp
        $url   = URL_CACHE . '/img/' . $hash . '.webp?m=' . $this->datum; // Changed extension to .webp
        
        if (!file_exists($cache) || @filemtime($cache) < $this->datum) {
            pripravCache(CACHE . '/img/'); // Ensure directory exists
            $this->uloz($cache);
        }
        return $url;
    }

    static function zeSouboru(string $nazev): self
    {
        return new self($nazev);
    }

}
