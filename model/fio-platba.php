<?php

/**
 * Platba načtená z fio api (bez DB reprezentace)
 */
class FioPlatba {

  private $r;

  /**
   * Platba se vytváří z asociativního pole s klíči odpovídajícími názvům atributů v fio api
   * viz http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
   */
  protected function __construct($pole) {
    $this->r = $pole;
  }

  /** Cacheuje a zpracovává surovou rest odpověď (kvůli limitu 30s na straně FIO) */
  protected static function cached($url) {
    $d = SPEC . '/fio';
    $f = $d . '/' . md5($url) . '.json';
    if(!is_dir($d))
      mkdir($d);
    if(@filemtime($f) < time() - 60)
      file_put_contents($f, file_get_contents($url));
    return preg_replace('@"value":([\d\.]+),@', '"value":"$1",', file_get_contents($f)); // konverze čísel na stringy kvůli velkým ID
  }

  /** Objem platby (kladný pro příchozí, záporný pro odchozí) */
  function castka() {
    return $this->r['Objem'];
  }

  /** Vrací ID jako string (64bitů int) */
  function id() {
    return $this->r['ID pohybu'];
  }

  /** Variabilní symbol */
  function vs() {
    return @$this->r['VS'];
  }

  /** Zpráva pro příjemce */
  function zprava() {
    return @$this->r['Zpráva pro příjemce'];
  }

  /** Vrátí platby za posledních $dni dní */
  static function zPoslednichDni($dni) {
    return self::zRozmezi(
      (new DateTime())->sub(new DateInterval('P'.$dni.'D')),
      new DateTime()
    );
  }

  protected static function zRozmezi(DateTime $od, DateTime $do) {
    $od = $od->format('Y-m-d');
    $do = $do->format('Y-m-d');
    $token = FIO_TOKEN;
    $url = "https://www.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.json";
    return self::zUrl($url);
  }

  /** Vrátí platby načtené z jsonu na dané url */
  protected static function zUrl($url) {
    $platby = json_decode(self::cached($url))->accountStatement->transactionList;
    $platby = $platby ? $platby->transaction : [];
    $o = [];
    foreach($platby as $p) {
      $o[] = self::zPlatby($p);
      //$o[id]?
    }
    return $o;
  }

  /** Vrátí platbu načtenou z předaného elementu z jsonového pole ...->transaction */
  protected static function zPlatby(StdClass $platba) {
    $pole = [];
    foreach($platba as $sloupec) {
      if($sloupec) {
        $pole[$sloupec->name] = $sloupec->value;
      }
    }
    return new self($pole);
  }

}
