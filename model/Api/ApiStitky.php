<?php

namespace Gamecon\Api;

require_once __DIR__ . '/../../admin/scripts/modules/aktivity/_editor-tagu.php';

class ApiStitky {
  static function apiStitky() {
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

    return $res;
  }
}
