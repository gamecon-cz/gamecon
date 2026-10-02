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

foreach (\Gamecon\Shop\Shop::polozkyRychlehoProdeje($rocnik) as $r) {
    $res[] = [
        'nazev' => $r['nazev'],
        'zbyva' => intvalOrNull($r['zbyva']),
        'id'    => intval($r['id_predmetu']),
        'cena'  => intval($r['cena']),
    ];
}

echo json_encode($res, $config);
