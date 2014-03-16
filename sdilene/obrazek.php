<?php

/**
 * Obrázek a základní manipulace s ním využívající imagemagic
 */

class Obrazek
{

  protected $o;       // interní reprezentace obrázku - resource
  protected $soubor;  // cesta a název souboru s pův. obrázkem

  const FILL = 1;
  const FIT  = 2;

  /** Konstruktor načítající resource s obrázkem a název pův. souboru */
  protected function __construct($o, $soubor)
  {
    $this->o = $o;
    $this->soubor = $soubor;
  }

  /** Interní metoda pro fill/fit s nastaveným režimem */
  protected function ff($nw, $nh, $rezim)
  {
    $novy = imagecreatetruecolor($nw, $nh);
    $ow = imagesx($this->o);
    $oh = imagesy($this->o);
    $origPomer  = $oh / $ow;
    $pomerStran = $nh / $nw;
    if( $pomerStran < $origPomer XOR $rezim == self::FIT ) {
      // přesah na výšku
      $sw = $ow;
      $sh = $ow*$pomerStran;
      $sx = 0;
      $sy = $oh/2 - $sh/2;
    } else {
      // přesah na šířku
      $sw = $oh/$pomerStran;
      $sh = $oh;
      $sx = $ow/2 - $sw/2;
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

  /** Zvětší proporciálně obrázek aby vyplnil $nw×$nh */
  function fill($nw, $nh)
  {
    $this->ff($nw, $nh, self::FILL);
  }

  /** Zvětší proporcionálně a doplní černými pruhy */
  function fit($nw, $nh)
  {
    $this->ff($nw, $nh, self::FIT);
  }

  function height()
  {
    return imagesy($this->o);
  }

  function ratio($nr=null)
  {
    if(!$nr) return imagesx($this->o) / imagesy($this->o);
    $ow = imagesx($this->o);
    $oh = imagesy($this->o);
    $or = $this->ratio();
    if($or < $nr)
      $this->resize($oh*$nr, $oh);
    else
      $this->resize($ow, $ow/$nr);
  }

  function ratioFill($newRatio)
  {
    if($this->ratio() < $newRatio)
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

  function ratioFit($newRatio)
  {
    if($this->ratio() > $newRatio)
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
  function reduce($nw, $nh)
  {
    if($this->width() > $nw)
      $this->resize($nw, $nh);
  }

  /** Změní velikost obrázku */
  function resize($nw, $nh)
  {
    $novy = imagecreatetruecolor($nw, $nh);
    $ow = imagesx($this->o);
    $oh = imagesy($this->o);
    imagecopyresampled($novy, $this->o,
      0, 0,     //dst x,y
      0, 0,     //src x,y
      $nw, $nh, //dst w,h
      $ow, $oh  //scr w,h
    );
    $this->o = $novy;
  }

  /** Uloží obrázek, přepíše původní */
  function uloz()
  {
    imagejpeg($this->o, $this->soubor, 98); // uložit
  }

  function width()
  {
    return imagesx($this->o);
  }

  /** Načte obrázek z JPG souboru */
  static function zJpg($soubor)
  {
    $o = imagecreatefromjpeg($soubor);
    if($o === false) throw new Exception('Obrázek se nepodařilo načíst.');
    return new self($o, $soubor);
  }

  /** Stáhne a nahraje obrázek z url */
  static function zUrl($url, $soubor)
  {
    $o = imagecreatefromjpeg($url);
    if($o === false) throw new Exception('Obrázek se nepodařilo načíst.');
    return new self($o, $soubor);
  }

}
