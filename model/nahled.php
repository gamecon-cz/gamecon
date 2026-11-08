<?php

/**
 * Náhled / prohlížeč obrázku s cacheováním atd..
 */

class Nahled
{

    private        $s       = null;
    private        $v       = null;
    private        $mod     = null;
    private string $soubor;
    private ?int   $datum;         // poslední změna orig. souboru
    private        $kvalita = 92;  // kvalita exportu na stupnici WebP a JPEG, AVIF dostane odpovídající, viz ImagickAvif::getKvalitaAvifPodleWebp()
    private bool   $jpeg    = false;

    const PASUJ       = 1;
    const POKRYJ      = 2;
    const POKRYJ_OREZ = 3;
    const OMEZ        = 4;

    public static function zeSouboru(string $nazev): self
    {
        return new self($nazev);
    }

    private function __construct(string $soubor)
    {
        if (!file_exists($soubor) || !is_readable($soubor)) {
            throw new RuntimeException('Obrázek neexistuje nebo není čitelný. Hledán na ' . $soubor);
        }
        $this->soubor = $soubor;
        $this->datum  = @filemtime($this->soubor)
            ?: null;
        if (!$this->datum) {
            throw new RuntimeException('Nepodařilo se zjistit datum modifikace obrázku: ' . $soubor);
        }
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    public function __toString()
    {
        try {
            return $this->url();
        } catch (Exception $e) {
            return ''; // __toString nesmí vyhazovat výjimky
        }
    }

    public function soubor(): string
    {
        return $this->soubor;
    }

    /** Nastaví kvalitu exportu na stupnici WebP a JPEG */
    public function kvalita(int | string $q): static
    {
        $this->kvalita = (int)$q;

        return $this;
    }

    /** Zmenší obrázek aby pasoval do obdelníku s šířkou $s a výškou $v */
    public function pasuj(
        $s,
        $v = null,
    ): Nahled {
        // make sure the image is not made larger
        if ($this->s <= $s && $this->v <= $v) {
            return $this;
        }

        return $this->mod($s, $v, self::PASUJ);
    }

    /** Zmenší proporčně obrázek aby šířka byla min $s a výška min $v */
    public function pokryj(
        $s,
        $v,
    ): Nahled {
        return $this->mod($s, $v, self::POKRYJ);
    }

    /** Zmenší obrázek, aby delší strana nebyla delší než $nejvic px, zachová poměr stran a nikdy nezvětšuje */
    public function omez(int $nejvic): Nahled
    {
        return $this->mod($nejvic, $nejvic, self::OMEZ);
    }

    /** Zmenší obrázek aby pokrýval šířku i výšku, vystředí a ořízne přebytek */
    public function pokryjOrez(
        $s,
        $v,
    ): Nahled {
        return $this->mod($s, $v, self::POKRYJ_OREZ);
    }

    /**
     * Náhled v JPEG pro toho, kdo AVIF nezobrazí: sociální sítě a chatovací aplikace u `og:image`. Zbytek webu čte
     * prohlížeč, a ten ho umí.
     */
    public function jakoJpeg(): static
    {
        $this->jpeg = true;

        return $this;
    }

    /** Vrátí url obrázku, je možné ji cacheovat navždy */
    public function url(): string
    {
        $format    = $this->getFormat();
        $pripona   = $format === 'JPEG'
            ? 'jpg'
            : strtolower($format);
        // WebP má hash jako před AVIF, takže se tam, kde zůstává, nic nepřegeneruje a stará cache platí dál
        $verze     = $format === 'WEBP'
            ? 'v2_webp'
            : 'v3_' . $pripona;
        $hash      = md5($this->soubor . $this->mod . $this->v . $this->s . $this->kvalita . $verze);
        $cache     = CACHE . '/img/' . $hash . '.' . $pripona;
        $url       = URL_CACHE . '/img/' . $hash . '.' . $pripona . '?m=' . $this->datum;

        if (!file_exists($cache) || @filemtime($cache) < $this->datum) {
            pripravCache(CACHE . '/img');
            $this->uloz($cache, $format);
        }

        return $url;
    }

    /**
     * AVIF, když ho tenhle ImageMagick zapíše správně i s průhledností, jinak WebP jako dřív. Formát musí být znám dřív,
     * než se soubor přečte, je v adrese náhledu, a průhlednost se z přípony nepozná. ImageMagick 6.9.11 (dnešní ostrá)
     * AVIF zapisuje i čte špatně a dvanáctkrát pomaleji než WebP, což u náhledů, které vznikají za běhu, nejde.
     *
     * @return 'AVIF'|'WEBP'|'JPEG'
     */
    private function getFormat(): string
    {
        if ($this->jpeg) {
            return 'JPEG';
        }

        return \Gamecon\Web\ImagickAvif::isAvifWritable(true)
            ? 'AVIF'
            : 'WEBP';
    }

    private function mod(
        $s,
        $v,
        $mod,
    ): Nahled {
        $this->mod = $mod;
        $this->s   = $s
            ? (int)$s
            : null;
        $this->v   = $v
            ? (int)$v
            : null;

        return $this;
    }

    /** Uloží stávající soubor s požadovanými úpravami do zadaného formátu */
    private function uloz(string $cil, string $format): void
    {
        try {
            $imagick = new Imagick($this->soubor);
            // ImageMagick 6 hlásí AVIF jako HEIC
            if (in_array($imagick->getImageFormat(), ['AVIF', 'HEIC'], true)) {
                \Gamecon\Web\ImagickAvif::pripravPoNacteni($imagick, $this->soubor);
            }
            // fotka z telefonu má pixely natočené a v EXIFu, jak ji otočit; prohlížeč to u originálu udělá sám
            $imagick->autoOrient();

            $s = $this->s
                ?: $imagick->getImageWidth();
            $v = $this->v
                ?: $imagick->getImageHeight();

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
                    case self::OMEZ:
                        // pouze zmenšit
                        if ($imagick->getImageWidth() > $s || $imagick->getImageHeight() > $v) {
                            $imagick->thumbnailImage($s, $v, true, false);
                        }
                        break;
                }
            }

            switch ($format) {
                case 'AVIF':
                    \Gamecon\Web\ImagickAvif::nastavZapis($imagick, \Gamecon\Web\ImagickAvif::getKvalitaAvifPodleWebp($this->kvalita));
                    break;
                case 'JPEG':
                    // JPEG průhlednost neumí a ImageMagick by průhledná místa zčernal
                    $imagick->setImageBackgroundColor(new ImagickPixel('white'));
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                    $imagick->setImageFormat('JPEG');
                    $imagick->setImageCompressionQuality($this->kvalita);
                    break;
                default:
                    $imagick->setImageFormat('WEBP');
                    $imagick->setImageCompressionQuality($this->kvalita);
            }

            // kódování AVIF trvá vteřiny a soubor, který už je na místě, ale ještě není dopsaný, by návštěvník dostal prázdný
            $obsah = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();
            (new \Symfony\Component\Filesystem\Filesystem())->dumpFile($cil, $obsah);
        } catch (ImagickException $e) {
            throw new RuntimeException("Chyba při zpracování obrázku (Imagick): " . $e->getMessage(), 0, $e);
        }
    }

}
