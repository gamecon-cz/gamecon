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
    protected $kvalita = 92;  // kvalita exportu (0-100 for WebP as well)

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
            // This case should ideally be caught by the file_exists check,
            // but filemtime can fail for other reasons.
            throw new RuntimeException('Nepodařilo se zjistit datum modifikace obrázku: ' . $soubor);
        }
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    function __toString()
    {
        try {
            return $this->url();
        } catch (Exception $e) {
            // Log error $e->getMessage()
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

            // Set large dimensions if not specified, to prevent unwanted scaling
            // when only format conversion is desired.
            $s = $this->s ?: $imagick->getImageWidth();
            $v = $this->v ?: $imagick->getImageHeight();
            
            // Ensure s and v are positive for Imagick operations
            $s = max(1, (int)$s);
            $v = max(1, (int)$v);

            if ($this->mod) { // Apply modifications only if a mod is set
                switch ($this->mod) {
                    case self::PASUJ:
                        // Resize to fit within dimensions, maintaining aspect ratio
                        $imagick->thumbnailImage($s, $v, true, false);
                        break;
                    case self::POKRYJ:
                        // Resize to fill dimensions, maintaining aspect ratio, then crop
                        $imagick->cropThumbnailImage($s, $v);
                        break;
                    case self::POKRYJ_OREZ:
                        // Resize to fill dimensions, maintaining aspect ratio, then crop
                        $imagick->cropThumbnailImage($s, $v);
                        break;
                }
            }

            $imagick->setImageFormat('WEBP');
            $imagick->setImageCompressionQuality($this->kvalita);
            
            // Optional: For more control over WebP options
            // if ($this->kvalita == 100) {
            //    $imagick->setOption('webp:lossless', 'true');
            // }
            // $imagick->setOption('webp:method', '4'); // 0 (fastest) to 6 (slowest, best quality)

            $imagick->writeImage($cil);
            $imagick->clear();
            $imagick->destroy();
        } catch (ImagickException $e) {
            // Log error: "Imagick error: " . $e->getMessage() . " for file " . $this->soubor
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
