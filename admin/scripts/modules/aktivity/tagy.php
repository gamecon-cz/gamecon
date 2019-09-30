<?php

/**
 * Stránka pro přehled všech tagů k aktivitám
 *
 * nazev: Tagy
 * pravo: 102
 */

require_once(__DIR__ . '/_editor-tagu.php');

$editorTagu = new EditorTagu();

if ($zpracovanyNovyTag = $editorTagu->zpracujTag()) {
  header('Content-Type: application/json');
  echo json_encode(['tag' => $zpracovanyNovyTag['tag'] ?? [], 'errors' => $zpracovanyNovyTag['errors'] ?? []]);
  exit;
}

$tpl = new XTemplate('tagy.xtpl');

$result = dbQuery('SELECT sjednocene_tagy.id, kategorie_sjednocenych_tagu.nazev AS nazev_kategorie,
       sjednocene_tagy.id_kategorie_tagu, sjednocene_tagy.nazev, sjednocene_tagy.poznamka
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu on sjednocene_tagy.id_kategorie_tagu = kategorie_sjednocenych_tagu.id
ORDER BY sjednocene_tagy.nazev
');
$lines = mysqli_num_rows($result);
for ($i = 0; $row = mysqli_fetch_assoc($result); $i++) {
  $mappedTag = [
    'id' => $row['id'],
    'nazev' => $row['nazev'],
    'nazev_kategorie' => $row['nazev_kategorie'],
    'id_kategorie_tagu' => $row['id_kategorie_tagu'],
    'poznamka' => $row['poznamka']
  ];
  $encodedTag = [];
  foreach ($mappedTag as $tagKey => $tagValue) {
    $encodedTag[$tagKey] = htmlspecialchars($tagValue);
  }
  unset($tagValue);
  $tpl->assign($encodedTag);
  $tpl->assign('tag_json', htmlspecialchars(json_encode($mappedTag, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT)));
  $tpl->parse('tagy.tag');
}

$tpl->assign('editorTaguHtmlId', EditorTagu::EDITOR_TAGU_HTML_ID);
$tpl->assign('editorTaguData', EditorTagu::EDITOR_TAGU_DATA);
$tpl->parse('tagy');
$tpl->out('tagy');

echo $editorTagu->novyTag();
