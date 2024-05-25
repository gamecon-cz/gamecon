<?php

use Gamecon\Cas\DateTimeCz;

require_once __DIR__ . '/../../../admin/scripts/modules/aktivity/_editor-tagu.php';


$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] != "GET") {
  return;
}


$editorTagu = new \EditorTagu();


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


$data = json_encode($res, $config);

$etag = md5($data);

$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';

if ($ifNoneMatch === $etag) {
    header("HTTP/1.1 304 Not Modified");
    exit();
}

header("Etag: $etag");
echo $data;
