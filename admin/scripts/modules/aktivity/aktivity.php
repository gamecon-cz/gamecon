<?php

/**
 * Stránka pro tvorbu a správu aktivit.
 *
 * nazev: Aktivity
 * pravo: 102
 */

if (!empty($_GET['update_code'])) { // TODO REMOVE
  exec('git pull 2>&1', $output, $returnValue);
  print_r($output);
  exit($returnValue);
}

if (post('smazat')) {
  $a = Aktivita::zId(post('aktivitaId'));
  $a->smaz();
  back();
}

if (post('publikovat')) {
  dbQueryS('UPDATE akce_seznam SET stav=4 WHERE id_akce=$0', [post('aktivitaId')]); // TODO převést do modelu
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
  dbQuery('UPDATE akce_seznam SET stav=1 WHERE stav=5 AND rok=' . ROK); // TODO převést do modelu
  back();
}

if (post('instance')) {
  Aktivita::zId(post('aktivitaId'))->instanciuj();
  back();
}

[$filtr, $razeni] = include __DIR__ . '/_filtr-moznosti.php';
$aktivity = Aktivita::zFiltru($filtr, $razeni);

$tpl = new XTemplate('aktivity.xtpl');

$currentRequestUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$tpl->assign('urlProImport', $currentRequestUrl . '/' . basename(__DIR__ . '/import.php', '.php'));
$tpl->assign('urlProExport', $currentRequestUrl . '/' . basename(__DIR__ . '/export.php', '.php'));
$tpl->parse('aktivity.importExport');

$typy = dbArrayCol('SELECT id_typu, typ_1p FROM akce_typy');
$typy[0] = '';

$mistnosti = dbArrayCol('SELECT id_lokace, nazev FROM akce_lokace');

foreach ($aktivity as $aktivita) {
  $r = $aktivita->rawDb();
  $tpl->assign([
    'id_akce' => $aktivita->id(),
    'nazev_akce' => $aktivita->nazev(),
    'tagy' => implode(' | ', $aktivita->tagy()),
    'cas' => $aktivita->denCas(),
    'organizatori' => $aktivita->orgJmena(),
    // TODO fixnout s lepším ORM
    'typ' => $typy[$r['typ']],
    'mistnost' => $mistnosti[$r['lokace']],
  ]);
  if ($r['patri_pod']) $tpl->parse('aktivity.aktivita.instSymbol');
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
