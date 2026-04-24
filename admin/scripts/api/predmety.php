<?php

use Gamecon\Kfc\ObchodMrizkaBunka;
use Gamecon\Kfc\ObchodMrizka;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET');
    echo json_encode(['error' => '405 Method Not Allowed']);
    exit;
}

if (empty($u)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => '403 Forbidden']);
    exit;
}

/*
  GET api/predmety
  response: {
    nazev: string,
    zbyva: number | undefined,
    id: number,
    cena: number,
  }[]
*/

$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$rocnik = (int)ROCNIK;

// GET
$vsechny = ObchodMrizka::zVsech();
$bunky   = ObchodMrizkaBunka::zVsech();
$res     = [];

// TODO: přesunout do nějaké DB třídy
$o = dbQuery('
  SELECT
    CONCAT(nazev," ",model_rok) as nazev,
    kusu_vyrobeno - COUNT(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena
  FROM shop_predmety p
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu AND n.rok = ' . $rocnik . ')
  WHERE p.stav > 0
    AND p.model_rok = ' . $rocnik . '
  GROUP BY p.id_predmetu
  ORDER BY nazev');

while ($r = mysqli_fetch_assoc($o)) {
    $res[] = [
        'nazev' => $r['nazev'],
        'zbyva' => intvalOrNull($r['zbyva']),
        'id'    => intval($r['id_predmetu']),
        'cena'  => intval($r['cena']),
    ];
}

echo json_encode($res, $config);
