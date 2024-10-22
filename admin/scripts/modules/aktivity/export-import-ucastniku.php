<?php
/**
 * Stránka pro hromadný export účastníků.
 *
 * nazev: Export & Import účastníků
 * pravo: 102
 * submenu_group: 3
 * submenu_order: 3
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

$template = new XTemplate(__DIR__ . '/export-import-ucastniku.xtpl');

$urlNaAktivity = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..';
$template->assign('urlNaAktivity', $urlNaAktivity);

require __DIR__ . '/_import-ucastniku.php';

require __DIR__ . '/_export-ucastniku.php';
