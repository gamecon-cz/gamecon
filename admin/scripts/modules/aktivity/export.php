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

if (isset($_GET['code'])) {
  $googleApiClient->authorizeByCode($_GET['code']);
  oznameni('Spárování s Google bylo úspěšné', false);
  // redirect to remove code from URL and avoid repeated but invalid re-authorization by the same code
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
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
