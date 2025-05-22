<?php

use Gamecon\Aktivita\EditorTagu;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] != "GET") {
  return;
}

$editorTagu = new EditorTagu($systemoveNastaveni->cachedDb());

$res = $editorTagu->getTagy();
$res = array_map(
  static function ($stitek) {
    return  [
      'id' => (int)$stitek['id'],
      'nazev' => $stitek['nazev'],
      'nazevKategorie' => $stitek['nazev_kategorie'],
      // 'nazevHlavniKategorie' => $stitek['nazev_hlavni_kategorie'],
      // 'idKategorieTagu' => $stitek['id_kategorie_tagu'],
      // 'poznamka' => $stitek['poznamka'],
    ];
  },
  $res
);

echo json_encode($res, $config);
