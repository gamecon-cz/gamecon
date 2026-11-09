<?php

/**
 * Zapne (`on`) nebo vypne (`off`) údržbový režim ostré přes rsync po SSH: `php udrzba/udrzbovy-rezim.php on`.
 *
 * Spouští ho GitHub Actions (.github/workflows/maintenance-ostra.yml). Potřebuje DEPLOY_RSYNC_TARGET, DEPLOY_SSH_KEY
 * a DEPLOY_SSH_KNOWN_HOSTS jako nasazení, viz dokumentace/nasazeni.md. Před zapnutím ověří, že ostrá má nasazenou stránku
 * údržby, a po přepnutí ověří požadavky na web i admin ostré, že odpovídají podle režimu; jinak skončí chybou.
 */

require_once __DIR__ . '/../nastaveni/zavadec-autoloader.php';
require_once __DIR__ . '/rsync-udrzbovy-rezim.php';

$stav = $argv[1] ?? '';
if (!in_array($stav, ['on', 'off'], true)) {
    echo "error: usage: php udrzba/udrzbovy-rezim.php on|off\n";
    exit(1);
}
$zapnout = $stav === 'on';

$ciloveRsync = (string)getenv('DEPLOY_RSYNC_TARGET');
if ($ciloveRsync === '') {
    echo "error: DEPLOY_RSYNC_TARGET is not set (GitHub secret OSTRA_DEPLOY_RSYNC_TARGET), the switch goes only over rsync\n";
    exit(1);
}

nadpis($zapnout
    ? "ZAPÍNÁM ÚDRŽBU OSTRÉ"
    : "VYPÍNÁM ÚDRŽBU OSTRÉ");

// adresy se zjistí a stránka se ověří před přepnutím, ať chyba nezanechá ostrou přepnutou a běh červený
$souborNastaveni = __DIR__ . '/../nastaveni/verejne-nastaveni-produkce.php';
$adresyKeKontrole = getMaintenanceCheckUrls($souborNastaveni);
if ($zapnout) {
    assertMaintenancePageDeployed(getMaintenanceDeployedPageUrl($souborNastaveni), 'fetchHttpResponse', MAINTENANCE_PAGE_MARKER);
}

setMaintenanceViaRsync($zapnout, __DIR__ . '/../' . MAINTENANCE_PAGE_FILE, $ciloveRsync);
waitForMaintenanceState($zapnout, $adresyKeKontrole, 'fetchHttpResponse', MAINTENANCE_PAGE_MARKER, 3, 5);
