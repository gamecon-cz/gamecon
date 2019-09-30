<?php

class EditorTagu
{
  public const EDITOR_TAGU_HTML_ID = 'editorTagu';
  public const EDITOR_TAGU_DATA = 'tag';
  private const POST_KLIC = 'aEditTag';
  private const ID_TAGU_KLIC = 'aEditTagId';
  private const KATEGORIE_TAGU_KLIC = 'aEditKategorieTagu';       // název proměnné, v které jsou kategorie tagů
  private const NAZEV_TAGU_KLIC = 'aEditNazevTagu';       // název proměnné, v které je název tagu
  private const POZNAMKA_TAGU_KLIC = 'aEditPoznamkaTagu';       // název proměnné, v které je poznámka k tagu

  public function novyTag() {
    $editorTaguSablona = new XTemplate(__DIR__ . '/_editor-tagu.xtpl');

    $vsechnyKategorieTagu = $this->getAllCategories();
    foreach ($vsechnyKategorieTagu as $idKategorie => $nazevKategorie) {
      $editorTaguSablona->assign('id_kategorie_tagu', $idKategorie);
      $editorTaguSablona->assign('nazev_kategorie', $nazevKategorie);
      $editorTaguSablona->assign('kategorie_selected', false);
      $editorTaguSablona->parse('editorTagu.kategorie');
    }

    $allTagNames = $this->getAllTagNames();
    $allTagNamesHtmlEncoded = array_map(
      function (string $tagName) {
        $tagName = strtolower($tagName);
        return htmlspecialchars($tagName);
      },
      $allTagNames
    );
    $allTagNamesJsonEncoded = json_encode($allTagNamesHtmlEncoded, JSON_UNESCAPED_UNICODE);
    $editorTaguSablona->assign('allTagNamesJson', $allTagNamesJsonEncoded);

    $editorTaguSablona->assign('editorTaguHtmlId', self::EDITOR_TAGU_HTML_ID);
    $editorTaguSablona->assign('editorTaguData', self::EDITOR_TAGU_DATA);
    $editorTaguSablona->assign('aEditTag', self::POST_KLIC);
    $editorTaguSablona->assign('aEditIdTagu', self::ID_TAGU_KLIC);
    $editorTaguSablona->assign('aEditNazevTagu', self::NAZEV_TAGU_KLIC);
    $editorTaguSablona->assign('aEditKategorieTagu', self::KATEGORIE_TAGU_KLIC);
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

  private function getAllTagNames(): array {
    return dbOneArray(
      'SELECT sjednocene_tagy.nazev
FROM sjednocene_tagy'
    );
  }

  public function zpracujTag(): array {
    if (empty($_POST[self::POST_KLIC])) {
      return [];
    }
    $values = $_POST[self::POST_KLIC];
    $nazevTagu = trim($values[self::NAZEV_TAGU_KLIC] ?? '');
    $idKategorieTagu = trim($values[self::KATEGORIE_TAGU_KLIC] ?? '');
    $idTagu = trim($values[self::ID_TAGU_KLIC] ?? '');
    $poznamkaTagu = trim($values[self::POZNAMKA_TAGU_KLIC] ?? '');
    $errors = [];
    if ($idTagu === '' && $nazevTagu === '') {
      $errors[] = 'Název tagu je prázdný';
    }
    if ($idKategorieTagu === '') {
      $errors[] = 'Kategorie tagu není vybrána';
    }
    if ($errors) {
      return ['errors' => $errors];
    }
    if ($idTagu) {
      try {
        $result = dbUpdate('sjednocene_tagy', ['nazev' => $nazevTagu, 'id_kategorie_tagu' => $idKategorieTagu, 'poznamka' => $poznamkaTagu], ['id' => $idTagu]);
      } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
        return ['errors' => ["Název tagu '$nazevTagu' už je obsazený: {$dbDuplicateEntryException->getMessage()}"]];
      }
    } else {
      $result = dbQuery(
        $query = 'INSERT IGNORE INTO sjednocene_tagy (id, id_kategorie_tagu, nazev, poznamka) VALUES (NULL, $0, $1, $2)',
        [$idKategorieTagu, $nazevTagu, $poznamkaTagu]
      );
    }
    if (!$result) {
      throw new \RuntimeException('Failed SQL execution of ' . dbLastQ());
    }
    if (!$idTagu) {
      $newTagId = dbInsertId(false /* do not raise exception if no ID */);
      if (!$newTagId) {
        return ['errors' => ["Tag '{$nazevTagu}' už existuje"]];
      }
      $idTagu = $newTagId;
    }
    return [
      'tag' => dbOneLine(
        'SELECT sjednocene_tagy.id, sjednocene_tagy.id_kategorie_tagu, kategorie_sjednocenych_tagu.nazev AS nazev_kategorie,
       sjednocene_tagy.nazev, sjednocene_tagy.poznamka
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu ON kategorie_sjednocenych_tagu.id = sjednocene_tagy.id_kategorie_tagu
WHERE sjednocene_tagy.id = $1',
        [$idTagu]
      )
    ];
  }
}
