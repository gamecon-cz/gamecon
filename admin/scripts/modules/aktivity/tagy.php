<?php

/**
 * Stránka pro přehled všech tagů k aktivitám
 *
 * nazev: Tagy
 * pravo: 102
 */

$tpl = new XTemplate('tagy.xtpl');

$result = dbQuery('SELECT sjednocene_tagy.id, kategorie_sjednocenych_tagu.nazev AS nazev_kategorie, sjednocene_tagy.nazev, sjednocene_tagy.poznamka
FROM sjednocene_tagy
JOIN kategorie_sjednocenych_tagu on sjednocene_tagy.id_kategorie_tagu = kategorie_sjednocenych_tagu.id
ORDER BY sjednocene_tagy.nazev
');
$lines = mysqli_num_rows($result);
for ($i = 0; $row = mysqli_fetch_assoc($result); $i++) {
  $tpl->assign(['id' => $row['id'], 'nazev' => $row['nazev'], 'nazev_kategorie' => $row['nazev_kategorie'], 'poznamka' => $row['poznamka']]);
  $tpl->parse('tagy.tag');
}

$tpl->parse('tagy');
$tpl->out('tagy');

require_once(__DIR__ . '/_editor-tagu.php');
$editorTagu = new EditorTagu();

if ($editorTagu->zpracujTag()) {
  back();
}

echo $editorTagu->novyTag();
