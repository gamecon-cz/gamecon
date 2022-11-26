<?php

/**
 * Obrázek a základní manipulace s ním využívající imagemagic
 */

class Obrazek
{

    protected $o;         // interní reprezentace obrázku - resource
    protected $original;  // originální načtený obrázek - pro detekci ne/změněnosti při ukládání
    protected $soubor;    // cesta a název souboru s pův. obrázkem

    const FILL = 1;
    const FIT  = 2;

    /** Konstruktor načítající resource s obrázkem a název pův. souboru */
    protected function __construct($o, $soubor) {
        $this->o        = $o;
        $this->original = $o;
        $this->soubor   = $soubor;
    }

    /** Interní metoda pro fill/fit s nastaveným režimem */
    protected function ff($nw, $nh, $rezim) {
        $novy       = imagecreatetruecolor($nw, $nh);
        $ow         = imagesx($this->o);
        $oh         = imagesy($this->o);
        $origPomer  = $oh / $ow;
        $pomerStran = $nh / $nw;
        if ($pomerStran < $origPomer xor $rezim == self::FIT) {
            // přesah na výšku
            $sw = $ow;
            $sh = $ow * $pomerStran;
            $sx = 0;
            $sy = $oh / 2 - $sh / 2;
        } else {
            // přesah na šířku
            $sw = $oh / $pomerStran;
            $sh = $oh;
            $sx = $ow / 2 - $sw / 2;
            $sy = 0;
        }
        // pouze zmenšit
        //if($sw < $nw || $sh < $nh) return;
        // resampling
        imagecopyresampled($novy, $this->o,
            0, 0,     //dst x,y
            $sx, $sy, //src x,y
            $nw, $nh, //dst w,h
            $sw, $sh  //scr w,h
        );
        $this->o = $novy;
    }

    /** Zvětší proporciálně obrázek aby vyplnil $nw×$nh, ořízne */
    function fill($nw, $nh) {
        $this->ff($nw, $nh, self::FILL);
    }

    /** Zmenší obrázek do daného bounding-boxu při zachování poměru stran */
    function fillCrop($maxw, $maxh) {
        $ratio = 1.0;
        if ($this->width() > $maxw) $ratio = $maxw / $this->width();
        if ($this->height() * $ratio < $maxh) $ratio = $maxh / $this->height();
        if ($ratio < 1.0) $this->resize($this->width() * $ratio, $this->height() * $ratio);
    }

    /** Zvětší proporcionálně a doplní černými pruhy */
    function fit($nw, $nh) {
        $this->ff($nw, $nh, self::FIT);
    }

    /** Zmenší obrázek do daného bounding-boxu při zachování poměru stran */
    function fitCrop($maxw, $maxh) {
        $ratio = 1.0;
        if ($this->width() > $maxw) $ratio = $maxw / $this->width();
        if ($this->height() * $ratio > $maxh) $ratio = $maxh / $this->height();
        if ($ratio < 1.0) $this->resize($this->width() * $ratio, $this->height() * $ratio);
    }

    function height() {
        return imagesy($this->o);
    }

    function ratio($nr = null) {
        if (!$nr) return imagesx($this->o) / imagesy($this->o);
        $ow = imagesx($this->o);
        $oh = imagesy($this->o);
        $or = $this->ratio();
        if ($or < $nr)
            $this->resize($oh * $nr, $oh);
        else
            $this->resize($ow, $ow / $nr);
    }

    function ratioFill($newRatio) {
        if ($this->ratio() < $newRatio)
            $this->ff(
                $this->width(),
                $this->width() / $newRatio,
                self::FILL);
        else
            $this->ff(
                $this->height() * $newRatio,
                $this->height(),
                self::FILL);
    }

    function ratioFit($newRatio) {
        if ($this->ratio() > $newRatio)
            $this->ff(
                $this->width(),
                $this->width() / $newRatio,
                self::FIT);
        else
            $this->ff(
                $this->height() * $newRatio,
                $this->height(),
                self::FIT);
    }

    /** Zmenší obrázek, pokud je moc velký */
    function reduce($nw, $nh) {
        if ($this->width() > $nw)
            $this->resize($nw, $nh);
    }

    /** Změní velikost obrázku */
    function resize($nw, $nh) {
        $nw   = (int)$nw;
        $nh   = (int)$nh;
        $novy = imagecreatetruecolor($nw, $nh);
        $ow   = imagesx($this->o);
        $oh   = imagesy($this->o);
        imagecopyresampled(
            $novy,
            $this->o,
            0, 0,     //dst x,y
            0, 0,     //src x,y
            $nw, $nh, //dst w,h
            $ow, $oh  //scr w,h
        );
        $this->o = $novy;
    }

    /** Uloží obrázek, přepíše původní */
    function uloz($cil = null, $kvalita = null): bool {
        if ($cil === null) {
            $cil = $this->soubor;
        }
        if ($this->o == $this->original) { // žádné změny
            return copy($this->soubor, $cil); // použít originál
        }
        return imagejpeg($this->o, $cil, $kvalita ?: 98); // uložit
    }

    function width() {
        return imagesx($this->o);
    }

    /** Načte obrázek z některého z podporovaných typů souboru */
    static function zSouboru($soubor): Obrazek {
        return static::zUrl($soubor, $soubor);
    }

    /** Stáhne a nahraje obrázek z url */
    static function zUrl($url, $soubor = null): Obrazek {
        $o = false;
        error_clear_last();
        $typ = @exif_imagetype($url);
        if ($typ === false) {
            $errorMessage = "Typ obrázku se nepodařilo zjistit ze zdroje '$url'";
            if ($url === $soubor) {
                $errorMessage .= sprintf(", velikost zdrojového souboru je %d bajtů", filesize($url));
            }
            $errorMessage .= sprintf(", detail '%s'", (error_get_last()['message'] ?? ''));
            throw new ObrazekException($errorMessage);
        }
        if ($typ === IMAGETYPE_JPEG) $o = @imagecreatefromjpeg($url);
        if ($typ === IMAGETYPE_PNG) $o = @imagecreatefrompng($url);
        if ($typ === IMAGETYPE_GIF) $o = @imagecreatefromgif($url);
        if ($o === false) {
            throw new ObrazekException(
                sprintf(
                    "Nepodporavný formát obrázku '%d' zjištěný z URL '%s'. Podporované jsou pouze JPEG (%d), PNG (%d) a GIF (%d)",
                    $typ,
                    $url,
                    IMAGETYPE_JPEG,
                    IMAGETYPE_PNG,
                    IMAGETYPE_GIF
                )
            );
        }
        return new self($o, $soubor ?: $url);
    }

}

class ObrazekException extends \RuntimeException
{
}
