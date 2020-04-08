<?php

/**
 * Stránka pro tvorbu a správu aktivit.
 *
 * nazev: Aktivity
 * pravo: 102
 */

if (post('smazat')) {
  $a = Aktivita::zId(post('aktivitaId'));
  $a->smaz();
  back();
}

if (post('publikovat')) {
  Aktivita::zId(post('aktivitaId'))->publikuj();
  back();
}

if (post('pripravit')) {
  Aktivita::zId(post('aktivitaId'))->priprav();
  back();
}

if (post('odpripravit')) {
  Aktivita::zId(post('aktivitaId'))->odpriprav();
  back();
}

if (post('aktivovat')) {
  Aktivita::zId(post('aktivitaId'))->aktivuj();
  back();
}

if (post('aktivovatVse')) {
  Aktivita::aktivujVsePripravene(ROK);
  back();
}

if (post('instance')) {
  Aktivita::zId(post('aktivitaId'))->instancuj();
  back();
}

require_once __DIR__ . '/_filtr-moznosti.php';

$filtrMoznosti = FiltrMoznosti::vytvorZGlobals(FiltrMoznosti::NEFILTROVAT_PODLE_ROKU);

$filtrMoznosti->zobraz();

[$filtr, $razeni] = $filtrMoznosti->dejFiltr();
$aktivity = Aktivita::zFiltru($filtr, $razeni);

if (defined('TESTING') && TESTING && !empty($filtr['typ']) && post('smazatVsechnyTypu')) {
  foreach ($aktivity as $aktivita) {
    $aktivita->smaz();
  }
  back();
}

$tpl = new XTemplate('aktivity.xtpl');

$currentRequestUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$exportImportUrl = $currentRequestUrl . '/export-import';
$tpl->assign('urlProExport', $exportImportUrl);
$tpl->assign('urlProImport', $exportImportUrl);
$tpl->parse('aktivity.exportImport');

if (defined('TESTING') && TESTING && !empty($filtr['typ'])) {
  $tpl->assign('pocet', count($aktivity));
  $idTypu = $filtr['typ'];
  $tpl->assign('id_typu', $idTypu);
  $tpl->assign('nazev_typu', Typ::zId($idTypu)->nazev());
  $tpl->parse('aktivity.smazatTyp');
}

$typy = dbArrayCol('SELECT id_typu, typ_1p FROM akce_typy');
$typy[0] = '';

$mistnosti = dbArrayCol('SELECT id_lokace, nazev FROM akce_lokace');

foreach ($aktivity as $aktivita) {
  $r = $aktivita->rawDb();
  $tpl->assign([
    'id_akce' => $aktivita->id(),
    'nazev_akce' => $aktivita->nazev(),
    'hinted' => $aktivita->tagy() ? 'hinted' : '',
    'cas' => $aktivita->denCas(),
    'organizatori' => $aktivita->orgJmena(),
    'typ' => $typy[$r['typ']],
    'mistnost' => $mistnosti[$r['lokace']] ?? '(žádná)',
  ]);
  if ($aktivita->tagy()) {
    $tpl->assign('tagy', implode(' | ', $aktivita->tagy()));
    $tpl->parse('aktivity.aktivita.hint');
  }

  if ($r['patri_pod']) $tpl->parse('aktivity.aktivita.symbolInstance');
  if ($r['stav'] == 0) $tpl->parse('aktivity.aktivita.tlacitka.publikovat');
  if ($r['stav'] == 4) $tpl->parse('aktivity.aktivita.tlacitka.pripravit');
  if ($r['stav'] == 5) $tpl->parse('aktivity.aktivita.tlacitka.odpripravit');
  if ($r['stav'] == 5) $tpl->parse('aktivity.aktivita.tlacitka.aktivovat');
  $tpl->parse('aktivity.aktivita.tlacitka');
  $tpl->parse('aktivity.aktivita');
}

if ($filtr == ['rok' => ROK]) {
  $tpl->parse('aktivity.aktivovatVse');
}

$tpl->parse('aktivity');
$tpl->out('aktivity');
