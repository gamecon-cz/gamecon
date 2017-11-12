<?php

class PerfectCache {

  private
    $slozka,
    $url;

  function __construct($slozka, $url) {
    $this->slozka = $slozka;
    $this->url = $url;
  }

  private function nactiSoubory($soubory) {
    $vysledek = [];
    $vyjimky  = [];

    foreach($soubory as $soubor) {
      if($soubor[0] == '!')
        $vyjimky  = array_merge($vyjimky, glob(substr($soubor, 1)));
      else
        $vysledek = array_merge($vysledek, glob($soubor));
    }

    $vysledek = array_diff($vysledek, $vyjimky);

    return $vysledek;
  }

  function sestavReact($soubory) {
    $out = '';
    $cil = $this->urciCil($soubory);

    // TODO načtení souborů jde do filesystému, tzn. v produkci celou tuto část zahodit
    $realneSoubory = $this->nactiSoubory($soubory);
    if($cil->jeStarsiNez($realneSoubory)) {
      $cil->vymaz();
      foreach($realneSoubory as $soubor) $cil->pridej($soubor);
    }

    return
      '<script type="text/babel" src="' .
      $this->url . '/' . $cil->nazevSouboru . '?v' . $cil->timestamp .
      '"></script>';
  }

  private function urciCil($soubory) {
    $slozene = implode('|', $soubory);
    $pripona = substr($slozene, strrpos($slozene, '.') + 1);
    if(strlen($pripona) > 4 || strlen($pripona) < 2) throw new Exception;
    //if($pripona == 'jsx') $pripona = 'js';
    return new Cil($this->slozka . '/' . substr(md5($slozene), 0, 8) . '.' . $pripona);
  }

}

class Cil {

  function __construct($soubor) {
    $this->soubor = $soubor;
    $this->nazevSouboru = basename($soubor);
    $this->timestamp = @filemtime($soubor);
  }

  function jeStarsiNez($soubory) {
    foreach($soubory as $soubor) if($this->timestamp < filemtime($soubor)) return true;
    return false;
  }

  function pridej($soubor) {
    file_put_contents($this->soubor, file_get_contents($soubor), FILE_APPEND);
    $this->timestamp = @filemtime($this->soubor);
  }

  function vymaz() {
    file_put_contents($this->soubor, '');
    $this->timestamp = @filemtime($this->soubor);
  }

}
