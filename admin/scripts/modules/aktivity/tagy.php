<?php

/**
 * Stránka pro přehled všech tagů k aktivitám
 *
 * nazev: Tagy
 * pravo: 102
 */

require_once(__DIR__ . '/_editor-tagu.php');

$editorTagu = new EditorTagu();

$zpracovanyTag = $editorTagu->pridejNovyTag();
if (!$zpracovanyTag) {
  $zpracovanyTag = $editorTagu->editujTag();
}
if ($zpracovanyTag) {
  header('Content-Type: application/json');
  echo json_encode([
    'tag' => $zpracovanyTag['tag'] ?? [],
    'errors' => $zpracovanyTag['errors'] ?? [],
    'tagIsNew' => $zpracovanyTag['isNew'] ?? null,
    'tagIsEdited' => $zpracovanyTag['isEdited'] ?? null
  ]);
  exit;
}

$tpl = new XTemplate('tagy.xtpl');

$result = dbQuery('SELECT sjednocene_tagy.id, sjednocene_tagy.nazev, sjednocene_tagy.poznamka,
       kategorie_sjednocenych_tagu.nazev AS nazev_kategorie,
       IF (kategorie_sjednocenych_tagu.id_hlavni_kategorie IS NULL, kategorie_sjednocenych_tagu.nazev, (SELECT hlavni_kategorie.nazev FROM kategorie_sjednocenych_tagu AS hlavni_kategorie WHERE hlavni_kategorie.id = kategorie_sjednocenych_tagu.id_hlavni_kategorie)) AS nazev_hlavni_kategorie,
       sjednocene_tagy.id_kategorie_tagu
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu on sjednocene_tagy.id_kategorie_tagu = kategorie_sjednocenych_tagu.id
ORDER BY kategorie_sjednocenych_tagu.poradi, sjednocene_tagy.nazev
');
$pouzitaHlavniKategorie = null;
$lines = mysqli_num_rows($result);
for ($i = 0; $row = mysqli_fetch_assoc($result); $i++) {
  $mappedTag = [
    'id' => $row['id'],
    'nazev' => $row['nazev'],
    'nazev_kategorie' => $row['nazev_kategorie'],
    'nazev_hlavni_kategorie' => $row['nazev_hlavni_kategorie'],
    'id_kategorie_tagu' => $row['id_kategorie_tagu'],
    'poznamka' => $row['poznamka']
  ];
  $encodedTag = [];
  foreach ($mappedTag as $tagKey => $tagValue) {
    $encodedTag[$tagKey] = htmlspecialchars($tagValue);
  }
  unset($tagValue);
  $tpl->assign(
    'hlavniKategorieTr',
    $row['nazev_hlavni_kategorie'] && (!$pouzitaHlavniKategorie || $row['nazev_hlavni_kategorie'] !== $pouzitaHlavniKategorie)
      ? '<tr><th colspan="100%"><h3 style="text-transform: capitalize">' . $row['nazev_hlavni_kategorie'] . '</h3></th></tr>'
      : ''
  );
  if ($row['nazev_hlavni_kategorie']) {
    $pouzitaHlavniKategorie = $row['nazev_hlavni_kategorie'];
  }
  $tpl->assign($encodedTag);
  $tpl->assign('tag_json', htmlspecialchars(json_encode($mappedTag, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT)));
  $tpl->parse('tagy.tag');
}

$tpl->assign('editorTaguHtmlId', EditorTagu::EDITOR_TAGU_HTML_ID);
$tpl->assign('editorTaguData', EditorTagu::EDITOR_TAGU_DATA);
$tpl->parse('tagy');
$tpl->out('tagy');

echo $editorTagu->getTagEditor();
