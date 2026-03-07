<?php

use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;

require_once __DIR__ . '/_cron_zavadec.php';

$cestaExportu = AnonymizovanaDatabaze::cestaExportu();
$datumExportu = AnonymizovanaDatabaze::datumPoslednihoExportu();
$dnesniExportUzExistuje = $datumExportu && $datumExportu->format('Y-m-d') === date('Y-m-d');

if (!$dnesniExportUzExistuje) {
    $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
    $commandName = BackgroundProcessService::COMMAND_ANONYMIZE_DB;

    if ($backgroundProcessService->isProcessRunning($commandName)) {
        logs('Anonymizace databáze již běží na pozadí.');
    } else {
        $workerScript = __DIR__ . '/../scripts/zvlastni/systemove-nastaveni/workers/_anonymize-db-worker.php';
        $backgroundProcessService->startBackgroundProcess($commandName, $workerScript);
        logs("Anonymizace databáze spuštěna na pozadí (worker: $workerScript).");
    }
} else {
    logs("Dnešní anonymizovaná databáze již existuje ($cestaExportu).");
}
