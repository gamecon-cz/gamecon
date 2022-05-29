<?php
/**
 * Stránka pro hromadný export aktivit.
 *
 * nazev: Export & Import
 * pravo: 102
 * submenu_group: 1
 * submenu_order: 2
 *
 * Google API credentials lze získat přes https://console.developers.google.com/
 * NEW PROJECT -> Gamecon ... -> Credentials -> CREATE CREDENTIALS -> OAuth client ID -> Application type = Web application; Name = gamecon.cz (třeba); Authorized Javascript origins = https://admin.gamecon.cz; Authorised redirect URIs = https://admin.gamecon.cz/aktivity/export-import -> Download; Library -> Google Drive API -> Enable; Library -> Google Sheets API -> Enable
 * Stažený soubor zkopírovat do Gameconu pod názvem nastaveni/google-api-client-secret.json - nezapomeň ho zkopírovat hlavně do produkce, protože soubor je ignorovaný Gitem (měl by být!) a při deploy se do produkce tedy nedostane.
 * Pokud crecentials uniknou (byť třeba commitnutím do Gitu a pushnutím na Github - Github umožňuje procházet i smazanou historii, takže přepsání historie už nepomůže), nezapomeň credentials přegenerovat - zase v https://console.developers.google.com/ přes Credentials ->  OAuth 2.0 Client IDs -> edit -> RESET SECRET a nahrát do produkce nový JSON.
 */

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\MissingGoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Vyjimkovac\Logovac;
use Gamecon\XTemplate\XTemplate;

if (($_GET['zpet'] ?? '') === 'aktivity') {
    back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..');
}

try {
    $googleApiCredentials = GoogleApiCredentials::createFromGlobals();
} catch (MissingGoogleApiCredentials $missingGoogleApiCredentials) {
    chyba('V nastavení chybí přístupy pro Google Sheets API. Kontaktujte Gamecon IT 💻.');
    exit;
}

/** @type \Uzivatel $u */
$currentUserId = $u->id();
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

$template = new XTemplate(__DIR__ . '/export-import.xtpl');

$urlNaAktivity = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..';
$template->assign('urlNaAktivity', $urlNaAktivity);

try {

    $googleDriveService = new GoogleDriveService($googleApiClient);
    $googleSheetsService = new GoogleSheetsService($googleApiClient, $googleDriveService);

    ob_start();
    // AUTHORIZATION
    if (!$googleApiClient->isAuthorized()) {
        $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
        $template->parse('autorizace');
        $template->out('autorizace');
    } else {
        require __DIR__ . '/_import.php';
    }
    $importOutput = ob_get_clean();

    require __DIR__ . '/_export.php';

    echo $importOutput;

} catch (GoogleConnectionException | \Google_Service_Exception $connectionException) {
    /** @var Logovac $vyjimkovac */
    $vyjimkovac->zaloguj($connectionException);
    chyba('Google Sheets API je dočasně nedostupné. Zkus to za chvíli znovu.');
    exit;
}
