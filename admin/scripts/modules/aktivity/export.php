<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\Export\ExporterAktivit;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;

/**
 * Stránka pro hromadný export aktivit.
 *
 * nazev: Export
 * pravo: 102
 */

if (!empty($_GET['update_code'])) {
  exec('git pull 2>&1', $output, $returnValue);
  print_r($output);
  exit($returnValue);
}

if ($_GET['zpet'] ?? '' === 'aktivity') {
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..');
}

/** @type \Uzivatel $u */
$googleApiClient = new GoogleApiClient(
  new GoogleApiCredentials(GOOGLE_API_CREDENTIALS),
  new GoogleApiTokenStorage(),
  $u->id()
);

if (isset($_GET['code'])) {
  $googleApiClient->authorizeByCode($_GET['code']);
  // redirect to remove code from URL and avoid repeated but invalid re-authorization by the same code
  reload();
}

[$filtr, $razeni] = include __DIR__ . '/_filtr-moznosti.php';
$aktivity = \Aktivita::zFiltru($filtr, $razeni);
$activityTypeIds = array_unique(
  array_map(
    static function (\Aktivita $aktivita) {
      return $aktivita->typId();
    },
    $aktivity
  )
);

$template = new \XTemplate('export.xtpl');

$template->assign('urlNaAktivity', $_SERVER['REQUEST_URI'] . '/..');

if (count($activityTypeIds) > 1) {
  $template->parse('export.neniVybranTyp');
} else if (count($activityTypeIds) === 0) {
  $template->parse('export.zadneAktivity');
} else if (count($activityTypeIds) === 1) {
  $activityTypeId = reset($activityTypeIds);

  if (!empty($_POST['activity_type_id']) && (int)$_POST['activity_type_id'] === (int)$activityTypeId && $googleApiClient->isAuthorized()) {
    $googleDriveService = new GoogleDriveService($googleApiClient);
    $googleSheetsService = new GoogleSheetsService($googleApiClient, $googleDriveService);
    $exportAktivit = new ExporterAktivit($u->id(), $googleDriveService, $googleSheetsService);
    $exportAktivit->exportAktivit($aktivity, ROK);
  } else {
    $template->assign('activityTypeId', $activityTypeId);

    $typAktivity = \Typ::zId($activityTypeId);
    $template->assign('nazevTypu', mb_ucfirst($typAktivity->nazev()));
    $template->assign('pocetAktivit', count($aktivity));
    $template->assign('exportDisabled', $googleApiClient->isAuthorized()
      ? ''
      : 'disabled'
    );

    $template->parse('export.exportovat');
  }
  if (!$googleApiClient->isAuthorized()) {
    $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
    $template->parse('export.autorizace');
  }
}

$template->parse('export');
$template->out('export');

// $googleDriveService = new GoogleDriveService($googleApiClient);

// $googleSheets = new GoogleSheetsService($googleApiClient, $googleDriveService);
