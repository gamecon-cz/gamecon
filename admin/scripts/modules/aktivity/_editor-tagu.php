<?php

class EditorTagu
{
  private $aEditKategorieTagu;
  private $aEditNazevTagu;
  private $aEditPoznamkaTagu;

  public function __constructor(string $aEditKategorieTagu, string $aEditNazevTagu, string $aEditPoznamkaTagu) {
    $this->aEditKategorieTagu = $aEditKategorieTagu;
    $this->aEditNazevTagu = $aEditNazevTagu;
    $this->aEditPoznamkaTagu = $aEditPoznamkaTagu;
  }

  public function novyTag() {
    $editorTaguSablona = new XTemplate(__DIR__ . '/_editor-tagu.xtpl');
    $vsechnyKategorieTagu = $this->getAllCategories();
    foreach ($vsechnyKategorieTagu as $idKategorie => $nazevKategorie) {
      $editorTaguSablona->assign('id_kategorie', $idKategorie);
      $editorTaguSablona->assign('nazev_kategorie', $nazevKategorie);
      $editorTaguSablona->assign('nazev_kategorie', $nazevKategorie);
      $editorTaguSablona->assign('kategorie_selected', false);
      $editorTaguSablona->parse('editorTagu.kategorie');
    }
    $editorTaguSablona->assign('aEditKategorieTagu', $this->aEditKategorieTagu);
    $editorTaguSablona->assign('aEditNazevTagu', $this->aEditNazevTagu);
    $editorTaguSablona->assign('aEditPoznamkaTagu', $this->aEditPoznamkaTagu);
    $editorTaguSablona->parse('editorTagu');

    return $editorTaguSablona->text('editorTagu');
  }

  private function getAllCategories(): array {
    return dbArrayCol(
      'SELECT kategorie_sjednocenych_tagu.id, kategorie_sjednocenych_tagu.nazev
FROM kategorie_sjednocenych_tagu
ORDER BY kategorie_sjednocenych_tagu.nazev'
    );
  }
}
