<?php

class EditorTagu
{
  private const POST_KLIC = 'aEditTag';
  private const KATEGORIE_TAGU_KLIC = 'aEditKategorieTagu';       // název proměnné, v které jsou kategorie tagů
  private const NAZEV_TAGU_KLIC = 'aEditNazevTagu';       // název proměnné, v které je název tagu
  private const POZNAMKA_TAGU_KLIC = 'aEditPoznamkaTagu';       // název proměnné, v které je poznámka k tagu

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
    $editorTaguSablona->assign('aEditTag', self::POST_KLIC);
    $editorTaguSablona->assign('aEditKategorieTagu', self::KATEGORIE_TAGU_KLIC);
    $editorTaguSablona->assign('aEditNazevTagu', self::NAZEV_TAGU_KLIC);
    $editorTaguSablona->assign('aEditPoznamkaTagu', self::POZNAMKA_TAGU_KLIC);
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

  public function editorZpracuj(): array {
    if(empty($_POST[self::POST_KLIC])) {
      return [];
    }
    $a = $_POST[self::POST_KLIC];
    return [];
  }
}
