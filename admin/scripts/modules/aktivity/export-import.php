<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\Export\ExporterAktivit;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Admin\Modules\Aktivity\Import\ImporterAktivit;

/**
 * Stránka pro hromadný export aktivit.
 *
 * nazev: Export & Import
 * pravo: 102
 */

if ($_GET['zpet'] ?? '' === 'aktivity') {
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..');
}

$googleApiCredentials = new GoogleApiCredentials(GOOGLE_API_CREDENTIALS);
/** @type \Uzivatel $u */
$googleApiClient = new GoogleApiClient(
  $googleApiCredentials,
  new GoogleApiTokenStorage($googleApiCredentials->getClientId()),
  $u->id()
);

if (!empty($_GET['flush-authorization'])) {
  $googleApiClient->flushAllAuthorizations();
}

if (isset($_GET['code'])) {
  $googleApiClient->authorizeByCode($_GET['code']);
  oznameni('Spárování s Google bylo úspěšné', false);
  // redirect to remove code from URL and avoid repeated but invalid re-authorization by the same code
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}

[$filtr, $razeni] = include __DIR__ . '/_filtr-moznosti.php';
$aktivity = \Aktivita::zFiltru($filtr, $razeni);
$activityTypeIdsFromFilter = array_unique(
  array_map(
    static function (\Aktivita $aktivita) {
      return $aktivita->typId();
    },
    $aktivity
  )
);

$template = new \XTemplate(__DIR__ . '/export-import.xtpl');

$template->assign('urlNaAktivity', $_SERVER['REQUEST_URI'] . '/..');

$googleDriveService = new GoogleDriveService($googleApiClient);
$googleSheetsService = new GoogleSheetsService($googleApiClient, $googleDriveService);

// EXPORT
if (count($activityTypeIdsFromFilter) > 1) {
  $template->parse('export.neniVybranTyp');
} else if (count($activityTypeIdsFromFilter) === 0) {
  $template->parse('export.zadneAktivity');
} else if (count($activityTypeIdsFromFilter) === 1) {
  $activityTypeIdFromFilter = reset($activityTypeIdsFromFilter);

  if (!empty($_POST['export_activity_type_id']) && (int)$_POST['export_activity_type_id'] === (int)$activityTypeIdFromFilter && $googleApiClient->isAuthorized()) {
    $exportAktivit = new ExporterAktivit($u->id(), $googleDriveService, $googleSheetsService);
    $nazevExportovanehoSouboru = $exportAktivit->exportujAktivity($aktivity, (string)ROK);
    oznameni(sprintf("Aktivity byly exportovány do Google sheets pod názvem '%s'", $nazevExportovanehoSouboru));
    exit;
  }
  $template->assign('activityTypeId', $activityTypeIdFromFilter);

  $typAktivity = \Typ::zId($activityTypeIdFromFilter);
  $template->assign('nazevTypu', mb_ucfirst($typAktivity->nazev()));
  $template->assign('pocetAktivit', count($aktivity));
  $template->assign('exportDisabled', $googleApiClient->isAuthorized()
    ? ''
    : 'disabled'
  );

  $template->parse('export.exportovat');
}

$template->parse('export');
$template->out('export');

// AUTHOIZACE
if (!$googleApiClient->isAuthorized()) {
  $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
  $template->parse('autorizace');
  $template->out('autorizace');
}

// IMPORT
if ($googleApiClient->isAuthorized()) {
  if (!empty($_POST['googleSheetId'])) {
    $importerAktivit = new ImporterAktivit($u->id(), $googleDriveService, $googleSheetsService, ROK);
    [$naimportovanoPocet, $nazevImportovanehoSouboru] = $importerAktivit->importujAktivity($_POST['googleSheetId']);
    oznameni(sprintf("Bylo naimportováno %d aktivit z Google sheet '%s'", $naimportovanoPocet, $nazevImportovanehoSouboru));
  }

  $spreadsheets = $googleSheetsService->getAllSpreadsheets();
  foreach ($spreadsheets as $spreadsheet) {
    $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId()));
    $template->assign('nazev', $spreadsheet->getName());
    $template->assign('url', $spreadsheet->getUrl());
    $template->assign('vytvorenoKdy', $spreadsheet->getCreatedAt()->relativni());
    $template->assign('upravenoKdy', $spreadsheet->getModifiedAt()->relativni());
    $template->assign('vytvorenoKdyPresne', $spreadsheet->getCreatedAt()->formatCasStandard());
    $template->assign('upravenoKdyPresne', $spreadsheet->getModifiedAt()->formatCasStandard());
    $template->parse('import.spreadsheets.spreadsheet');
  }
  $template->parse('import.spreadsheets');

  $template->parse('import');
  $template->out('import');
}
