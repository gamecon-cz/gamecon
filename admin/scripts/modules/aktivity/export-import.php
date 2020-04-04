<?php
/**
 * Stránka pro hromadný export aktivit.
 *
 * nazev: Export & Import
 * pravo: 102
 */

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Vyjimkovac\Logovac;

if ($_GET['zpet'] ?? '' === 'aktivity') {
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..');
}

/** @type \Uzivatel $u */
$currentUserId = $u->id();
$googleApiCredentials = new GoogleApiCredentials(GOOGLE_API_CREDENTIALS);
$googleApiClient = new GoogleApiClient(
  $googleApiCredentials,
  new GoogleApiTokenStorage($googleApiCredentials->getClientId()),
  $currentUserId
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

$template = new \XTemplate(__DIR__ . '/export-import.xtpl');

$urlNaAktivity = $_SERVER['REQUEST_URI'] . '/..';
$template->assign('urlNaAktivity', $urlNaAktivity);

try {

  $googleDriveService = new GoogleDriveService($googleApiClient);
  /** @noinspection PhpUnusedLocalVariableInspection */
  $googleSheetsService = new GoogleSheetsService($googleApiClient, $googleDriveService);

  ob_start();
  // AUTHOIZACE
  if (!$googleApiClient->isAuthorized()) {
    $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
    $template->parse('autorizace');
    $template->out('autorizace');
  } else {
    // IMPORT
    require __DIR__ . '/_import.php';
  }
  $importOutput = ob_get_clean();

  // EXPORT
  require __DIR__ . '/_export.php';

  echo $importOutput;

} catch (GoogleConnectionException | \Google_Service_Exception $connectionException) {
  /** @var Logovac $vyjimkovac */
  $vyjimkovac->zaloguj($connectionException);
  chyba('Google Sheets API je dočasně nedostupné. Zkus to za chvíli znovu.');
  exit;
}
