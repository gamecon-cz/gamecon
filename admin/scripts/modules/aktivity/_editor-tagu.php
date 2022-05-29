<?php
use Gamecon\XTemplate\XTemplate;

class EditorTagu
{
  public const EDITOR_TAGU_HTML_ID = 'editorTagu';
  public const EDITOR_TAGU_DATA = 'tag';
  private const POST_KLIC = 'aEditTag';
  private const ID_TAGU_KLIC = 'aEditTagId';
  private const KATEGORIE_TAGU_KLIC = 'aEditKategorieTagu';       // název proměnné, v které jsou kategorie tagů
  private const NAZEV_TAGU_KLIC = 'aEditNazevTagu';       // název proměnné, v které je název tagu
  private const POZNAMKA_TAGU_KLIC = 'aEditPoznamkaTagu';       // název proměnné, v které je poznámka k tagu

  public function getEditorTaguHtml() {
    $editorTaguSablona = new XTemplate(__DIR__ . '/_editor-tagu.xtpl');

    $vsechnyKategorieTagu = $this->getAllCategories();
    foreach ($vsechnyKategorieTagu as $kategorie) {
      $editorTaguSablona->assign(
        'optgroup_kategorie_start',
        $kategorie['hlavni']
          ? sprintf('<optgroup label="%s">', mb_ucfirst($kategorie['nazev']))
          : ''
      );
      $editorTaguSablona->assign('optgroup_kategorie_end', $kategorie['hlavni'] ? '</optgroup>' : '');
      $editorTaguSablona->assign('id_kategorie_tagu', $kategorie['id']);
      $editorTaguSablona->assign('nazev_kategorie', htmlspecialchars($kategorie['nazev']));
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
    $allTagNamesJsonEncoded = json_encode($allTagNamesHtmlEncoded, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
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
    return dbFetchAll(
      'SELECT kategorie_sjednocenych_tagu.id, kategorie_sjednocenych_tagu.nazev, kategorie_sjednocenych_tagu.id_hlavni_kategorie IS NULL AS hlavni
FROM kategorie_sjednocenych_tagu
ORDER BY kategorie_sjednocenych_tagu.poradi'
    );
  }

  private function getAllTagNames(): array {
    return dbArrayCol(
      'SELECT sjednocene_tagy.id, sjednocene_tagy.nazev
FROM sjednocene_tagy'
    );
  }

  public function pridejNovyTag(): array {
    $data = $this->parseTagRequest();
    if (!$data) { // nothing to do here
      return [];
    }
    if (!empty($data['errors'])) {
      return ['errors' => $data['errors']];
    }
    ['id' => $idTagu, 'nazev' => $nazevTagu, 'id_kategorie_tagu' => $idKategorieTagu, 'poznamka' => $poznamkaTagu] = $data;
    if ($idTagu) { // this is not a new tag but existing one
      return [];
    }
    $result = dbQuery(
      $query = 'INSERT IGNORE INTO sjednocene_tagy (id, id_kategorie_tagu, nazev, poznamka) VALUES (NULL, $0, $1, $2)',
      [$idKategorieTagu, $nazevTagu, $poznamkaTagu]
    );
    if (!$result) {
      throw new \RuntimeException('Failed SQL execution of ' . dbLastQ());
    }
    $newTagId = dbInsertId(false /* do not raise exception if no ID */);
    if (!$newTagId) {
      return ['errors' => ["Tag '{$nazevTagu}' už existuje"]];
    }
    return [
      'tag' => $this->getTagById($newTagId),
      'isNew' => true,
      'isEdited' => false,
    ];
  }

  private function parseTagRequest(): array {
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
    return ['id' => $idTagu, 'nazev' => $nazevTagu, 'id_kategorie_tagu' => $idKategorieTagu, 'poznamka' => $poznamkaTagu];
  }

  private function getTagById(int $id): array {
    return dbOneLine(
      'SELECT sjednocene_tagy.id, sjednocene_tagy.id_kategorie_tagu, kategorie_sjednocenych_tagu.nazev AS nazev_kategorie,
       sjednocene_tagy.nazev, sjednocene_tagy.poznamka
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu ON kategorie_sjednocenych_tagu.id = sjednocene_tagy.id_kategorie_tagu
WHERE sjednocene_tagy.id = $1',
      [$id]
    );
  }

  public function editujTag(): array {
    $data = $this->parseTagRequest();
    if (!$data) { // nothing to do here
      return [];
    }
    if (!empty($data['errors'])) {
      return ['errors' => $data['errors']];
    }
    ['id' => $idTagu, 'nazev' => $nazevTagu, 'id_kategorie_tagu' => $idKategorieTagu, 'poznamka' => $poznamkaTagu] = $data;
    if (!$idTagu) { // this is not an update
      return [];
    }
    try {
      $result = dbUpdate('sjednocene_tagy', ['nazev' => $nazevTagu, 'id_kategorie_tagu' => $idKategorieTagu, 'poznamka' => $poznamkaTagu], ['id' => $idTagu]);
    } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
      return ['errors' => ["Název tagu '$nazevTagu' už je obsazený: {$dbDuplicateEntryException->getMessage()}"]];
    }
    if (!$result) {
      throw new \RuntimeException('Failed SQL execution of ' . dbLastQ());
    }
    return [
      'tag' => $this->getTagById($idTagu),
      'isNew' => false,
      'isEdited' => true,
    ];
  }

  public function getTagy(): array {
    $result = dbQuery('SELECT sjednocene_tagy.id, sjednocene_tagy.nazev, sjednocene_tagy.poznamka,
       kategorie_sjednocenych_tagu.nazev AS nazev_kategorie,
       IF (kategorie_sjednocenych_tagu.id_hlavni_kategorie IS NULL, kategorie_sjednocenych_tagu.nazev, (SELECT hlavni_kategorie.nazev FROM kategorie_sjednocenych_tagu AS hlavni_kategorie WHERE hlavni_kategorie.id = kategorie_sjednocenych_tagu.id_hlavni_kategorie)) AS nazev_hlavni_kategorie,
       sjednocene_tagy.id_kategorie_tagu
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu on sjednocene_tagy.id_kategorie_tagu = kategorie_sjednocenych_tagu.id
ORDER BY kategorie_sjednocenych_tagu.poradi, sjednocene_tagy.nazev
');
    $mappedTags = [];
    for ($i = 0; $row = mysqli_fetch_assoc($result); $i++) {
      $mappedTag = [
        'id' => $row['id'],
        'nazev' => $row['nazev'],
        'nazev_kategorie' => $row['nazev_kategorie'],
        'nazev_hlavni_kategorie' => $row['nazev_hlavni_kategorie'],
        'id_kategorie_tagu' => $row['id_kategorie_tagu'],
        'poznamka' => $row['poznamka'],
      ];
      $mappedTags[] = $mappedTag;
    }

    return $mappedTags;
  }
}
