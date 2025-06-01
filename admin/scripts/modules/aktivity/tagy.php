<?php

/**
 * Stránka pro přehled všech tagů k aktivitám
 *
 * nazev: Štítky
 * pravo: 102
 * submenu_group: 4
 * submenu_order: 2
 */

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\XTemplate\XTemplate;
use Gamecon\Aktivita\EditorTagu;

$editorTagu = new EditorTagu($systemoveNastaveni->db());

$zpracovanyTag = $editorTagu->pridejNovyTag();
if (!$zpracovanyTag) {
    $zpracovanyTag = $editorTagu->editujTag();
}
if ($zpracovanyTag) {
    header('Content-Type: application/json');
    echo json_encode([
        'tag'         => $zpracovanyTag['tag'] ?? [],
        'errors'      => $zpracovanyTag['errors'] ?? [],
        'tagIsNew'    => $zpracovanyTag['isNew'] ?? null,
        'tagIsEdited' => $zpracovanyTag['isEdited'] ?? null,
    ]);
    exit;
}

$tpl = new XTemplate(__DIR__ . '/tagy.xtpl');

$pouzitaHlavniKategorie = null;
foreach ($editorTagu->getTagy() as $mappedTag) {
    $encodedTag = [];
    foreach ($mappedTag as $tagKey => $tagValue) {
        $encodedTag[$tagKey] = htmlspecialchars($tagValue);
    }
    $tpl->assign(
        'hlavniKategorieTr',
        $encodedTag['nazev_hlavni_kategorie'] && (!$pouzitaHlavniKategorie || $encodedTag['nazev_hlavni_kategorie'] !== $pouzitaHlavniKategorie)
            ? '<tr><th colspan="100%"><h3 style="text-transform: capitalize">' . $encodedTag['nazev_hlavni_kategorie'] . '</h3></th></tr>'
            : '',
    );
    $tpl->assign($encodedTag);
    $tpl->assign('tag_json', htmlspecialchars(json_encode($mappedTag, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT)));
    $tpl->parse('tagy.tag');
    if ($encodedTag['nazev_hlavni_kategorie']) {
        $pouzitaHlavniKategorie = $encodedTag['nazev_hlavni_kategorie'];
    }
}

$tpl->assign('editorTaguHtmlId', EditorTagu::EDITOR_TAGU_HTML_ID);
$tpl->assign('editorTaguData', EditorTagu::EDITOR_TAGU_DATA);
$tpl->parse('tagy');
$tpl->out('tagy');

echo $editorTagu->getEditorTaguHtml();
