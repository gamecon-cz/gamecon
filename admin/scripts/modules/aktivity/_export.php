<?php

use Gamecon\Admin\Modules\Aktivity\Export\ActivitiesExporter;

require_once __DIR__ . '/_filtr-moznosti.php';

$filtrMoznosti = FiltrMoznosti::vytvorZGlobals(FiltrMoznosti::FILTROVAT_PODLE_ROKU);

$filtrMoznosti->zobraz();

[$filtr, $razeni] = $filtrMoznosti->dejFiltr();
$aktivity = \Aktivita::zFiltru($filtr, $razeni);

$activityTypeIdsFromFilter = array_unique(
  array_map(
    static function (\Aktivita $aktivita) {
      return $aktivita->typId();
    },
    $aktivity
  )
);

if (count($activityTypeIdsFromFilter) > 1) {
  $template->parse('export.neniVybranTyp');
} else if (count($activityTypeIdsFromFilter) === 0) {
  $template->parse('export.zadneAktivity');
} else if (count($activityTypeIdsFromFilter) === 1) {
  $activityTypeIdFromFilter = reset($activityTypeIdsFromFilter);

  if (!empty($_POST['export_activity_type_id']) && (int)$_POST['export_activity_type_id'] === (int)$activityTypeIdFromFilter && $googleApiClient->isAuthorized()) {
    $baseUrl = (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $activitiesExporter = new ActivitiesExporter($u->id(), $googleDriveService, $googleSheetsService, $baseUrl);
    $nazevExportovanehoSouboru = $activitiesExporter->exportActivities($aktivity, (string)($filtr['rok'] ?? ROK));
    oznameni(sprintf("Aktivity byly exportovány do Google sheets pod názvem '%s'", $nazevExportovanehoSouboru));
    exit;
  }
  $template->assign('activityTypeId', $activityTypeIdFromFilter);

  $typAktivity = \Typ::zId($activityTypeIdFromFilter);
  $template->assign('nazevTypu', mb_ucfirst($typAktivity->nazev()) . (($filtr['rok'] ?? ROK) != ROK ? (' ' . $filtr['rok']) : ''));
  $pocetAktivit = count($aktivity);
  $pocetAktivitSlovo = 'aktivit';
  if ($pocetAktivit === 1) {
    $pocetAktivitSlovo = 'aktivitu';
  } elseif ($pocetAktivit > 1 && $pocetAktivit < 5) {
    $pocetAktivitSlovo = 'aktivity';
  }
  $template->assign('pocetAktivit', $pocetAktivit);
  $template->assign('pocetAktivitSlovo', $pocetAktivitSlovo);
  $template->assign('exportDisabled', $googleApiClient->isAuthorized()
    ? ''
    : 'disabled'
  );

  $template->parse('export.exportovat');
}

$template->parse('export');
$template->out('export');
