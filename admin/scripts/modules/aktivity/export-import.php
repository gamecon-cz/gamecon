<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
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
}

var_dump($googleApiClient);
var_dump($googleApiClient->isAuthorized());
if (!$googleApiClient->isAuthorized()) {
  echo $googleApiClient->getAuthorizationUrl();
  exit();
}

$googleSheets = new GoogleSheetsService($googleApiClient);
$userSpreadsheets = $googleSheets->getUserSpreadsheets($u->id());
