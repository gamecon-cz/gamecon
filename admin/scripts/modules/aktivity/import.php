<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;

/**
 * Stránka pro hromadný import aktivit.
 *
 * nazev: Import
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

$template = new \XTemplate('export.xtpl');

if (FALSE && !$googleApiClient->isAuthorized()) {
  $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
  $template->parse('autorizace');
  return;
}

$aktivityIds = array_filter(
  explode(',', $_POST['aktivity_ids'] ?? ''),
  static function ($idAktivity) {
    return $idAktivity !== '';
  }
);
if (!$aktivityIds) {
  $template->assign('urlNaAktivity', $_SERVER['REQUEST_URI'] . '/..');
  $template->parse('zadneAktivity');
  return;
}

$template->assign('aktivityIds', implode(';', $aktivityIds));
$template->assign('pocetAktivit', count($aktivityIds));
$template->parse('exportovat');

// $googleDriveService = new GoogleDriveService($googleApiClient);

// $googleSheets = new GoogleSheetsService($googleApiClient, $googleDriveService);
