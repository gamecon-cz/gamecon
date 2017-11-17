<?php

class Program {

  private
    $cacheSouboru,
    $jsElementId = 'cProgramElement', // TODO v případě použití více instancí řešit příslušnost k instancím
    $jsPromenna = 'cProgramPromenna',
    $jsObserveri = [];

  function __construct() {
    $this->cacheSouboru = new PerfectCache(CACHE, URL_CACHE);
  }

  function htmlHlavicky() {
    // TODO toto by mohla být statická metoda (pro případ více programů v stránce), ovšem může být problém s více komponentami vkládajícími opakovaně react a s více daty (např. jiné aktivity pro dvě instance programu)
    return '
      <script>
        var '.$this->jsPromenna.' = {
          "elementId": "'.$this->jsElementId.'",
          "aktivity": '.$this->jsonAktivity().',
          "linie": '.$this->jsonLinie().',
          "notifikace": '.$this->jsonNotifikace().'
        }
      </script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/babel-standalone/6.24.0/babel.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/react/15.1.0/react.min.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/react/15.1.0/react-dom.min.js"></script>
      ' . $this->cacheSouboru->sestavReact([
        __DIR__ . '/*.js',
        __DIR__ . '/*.jsx',
        '!' . __DIR__ . '/render.jsx'
      ]) . '
    ';
  }

  function htmlObsah() {
    // TODO na produkci udělat vkládaný react inline (jsou to cca 2 řádky)

    return '
      <div id="'.$this->jsElementId.'"></div>
      <script>
        var programData = '.$this->jsPromenna.'
      </script>
      ' . $this->cacheSouboru->sestavReact([__DIR__ . '/render.jsx']) . '
    ';
  }

  private function jsonAktivity() {
    // TODO aktuální rok
    // TODO listovat tech. aktivity jenom tomu, kdo je může vidět
    $q = dbQuery('
      SELECT
        a.id_akce as "id",
        a.nazev_akce as "nazev",
        a.typ as "linie"
      FROM akce_seznam a
      WHERE
        a.rok = $0 AND
        a.zacatek AND
        (a.stav IN(1,2,4,5) OR a.typ = 10)
    ', [ROK - 1]);

    return json_encode($q->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
  }

  private function jsonLinie() {
    $q = dbQuery('
      SELECT
        t.id_typu as "id",
        t.typ_1pmn as "nazev",
        t.poradi
      FROM akce_typy t
    ');

    $out = '{';
    foreach($q as $r) {
      $out .= '"'.$r['id'].'":{'.
        '"id":"'.$r['id'].'",'.  
        '"nazev":"'.$r['nazev'].'",'.
        '"poradi":'.$r['poradi'].
      '},';
    }
    $out[strlen($out) - 1] = '}';

    return $out;
  }

  private function jsonNotifikace() {
    return '[' . implode(',', $this->jsObserveri) . ']';
  }

  function zaregistrujJsObserver($nazevFunkce) {
    $this->jsObserveri[] = $nazevFunkce;
  }

}
