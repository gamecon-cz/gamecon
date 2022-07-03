<?php


/*
  GET api/predmety
  response: {
    nazev: string,
    zbyva: number | undefined,
    id: number,
    cena: number,
  }[]
*/

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// GET
$vsechny = ObchodMrizka::zVsech();
$bunky = ObchodMrizkaBunka::zVsech();
$res = [];

// TODO: přesunout do nějaké DB třídy
$o = dbQuery('
  SELECT
    CONCAT(nazev," ",model_rok) as nazev,
    kusu_vyrobeno-count(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena
  FROM shop_predmety p
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu)
  WHERE p.stav > 0
  GROUP BY p.id_predmetu
  ORDER BY model_rok DESC, nazev');



while ($r = mysqli_fetch_assoc($o)) {
  $res[] = [
    'nazev' => $r['nazev'],
    'zbyva' => intvalOrNull($r['zbyva']),
    'id' => intval($r['id_predmetu']),
    'cena' => intval($r['cena']),
  ];
}


echo json_encode($res, $config);
