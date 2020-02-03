<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;

/**
 * Stránka pro hromadný export a opětovný import aktivit.
 *
 * nazev: Export & import
 * pravo: 102
 */

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

$template = new \XTemplate('export-import.xtlp');

if (!$googleApiClient->isAuthorized()) {
  $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
  $template->parse('autorizace');
  return;
}

$googleDriveService = new GoogleDriveService($googleApiClient);

$googleSheets = new GoogleSheetsService($googleApiClient, $googleDriveService);
$userSpreadsheets = $googleSheets->getUserSpreadsheets($u->id());
var_dump($userSpreadsheets);
