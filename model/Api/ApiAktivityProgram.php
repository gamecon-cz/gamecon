<?php

namespace Gamecon\Api;

use Gamecon\Aktivita\Aktivita;

class ApiAktivityProgram {
  static function apiAktivityProgram($rok = ROK, \Uzivatel|null $u = null) {
    $res = [];

    $aktivity = Aktivita::zFiltru(["rok" => $rok]);

    foreach ($aktivity as $a) {
      if (!$a->zacatek()) continue;
      if (!$a->viditelnaPro($u)) continue;

      $vypraveci = array_map(function ($o) {
        return $o->jmenoNick();
      }, $a->organizatori());

      $stitkyId = $a->tagyId();

      $aktivitaRes = [
        'id'        =>  $a->id(),
        'nazev'     =>  $a->nazev(),
        'kratkyPopis' => $a->kratkyPopis(),
        'popis'     =>  $a->popis(),
        'obrazek'   =>  (string) $a->obrazek(),
        'vypraveci' =>  $vypraveci,
        'stitkyId'  =>  $stitkyId,
        // TODO: cenaZaklad by měla být číslo ?
        'cenaZaklad'      => intval($a->cenaZaklad()),
        'casText'   =>  $a->zacatek() ? $a->zacatek()->format('G') . ':00&ndash;' . $a->konec()->format('G') . ':00' : "",
        'cas'        =>  $a->zacatek() ? [
          'od'         => $a->zacatek()->getTimestamp() * 1000,
          'do'         => $a->konec()->getTimestamp() * 1000,
        ] : null,
        'linie'      =>  $a->typ()->nazev(),
      ];

      $vBudoucnu = $a->vBudoucnu();
      if ($vBudoucnu)
        $aktivitaRes['vBudoucnu'] = $vBudoucnu;

      $vdalsiVlne = $a->vDalsiVlne();
      if ($vdalsiVlne)
        $aktivitaRes['vdalsiVlne'] = $vdalsiVlne;

      $probehnuta = $a->probehnuta();
      if ($probehnuta)
        $aktivitaRes['probehnuta'] = $probehnuta;

      $jeBrigadnicka = $a->jeBrigadnicka();
      if ($jeBrigadnicka)
        $aktivitaRes['jeBrigadnicka'] = $jeBrigadnicka;

      $dite = $a->detiIds();
      if ($dite && count($dite))
        $aktivitaRes['dite'] = $dite;

      $res[] = $aktivitaRes;
    }
    return $res;
  }
}
