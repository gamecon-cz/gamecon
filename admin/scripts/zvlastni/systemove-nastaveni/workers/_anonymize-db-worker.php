#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker pro anonymizaci databáze na pozadí
 *
 * Spouští se z CRONu jednou denně nebo ručně z admin nastavení.
 */

require_once __DIR__ . '/../../../../../nastaveni/zavadec.php';

use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;

ini_set('memory_limit', '512M');
set_time_limit(600);

$commandName = BackgroundProcessService::COMMAND_ANONYMIZE_DB;
$backgroundProcessService = BackgroundProcessService::vytvorZGlobals();

$backgroundProcessService->registerShutdownHandler($commandName);

$logFile = LOGY . '/anonymize-db-' . date('Y-m-d_H-i-s') . '.log';
$startTime = microtime(true);

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("Začátek anonymizace databáze");

    $anonymizovanaDatabaze = AnonymizovanaDatabaze::vytvorZGlobals();

    logMessage("Obnovuji anonymní databázi...");
    $anonymizovanaDatabaze->obnov();

    logMessage("Exportuji do souboru...");
    $anonymizovanaDatabaze->exportujDoSouboru();

    $duration = round(microtime(true) - $startTime, 2);
    logMessage("Anonymizace databáze dokončena úspěšně za $duration sekund. Soubor: " . AnonymizovanaDatabaze::cestaExportu());

    exit(0);

} catch (\Throwable $e) {
    $duration = round(microtime(true) - $startTime, 2);

    $errorMessage = sprintf(
        "Chyba při anonymizaci databáze: %s\nSoubor: %s:%d\nTrvání: %s s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $duration,
    );

    logMessage($errorMessage);
    logMessage("Stack trace:\n" . $e->getTraceAsString());

    $backgroundProcessService->markProcessCompleted($commandName, false, $e->getMessage());

    exit(1);
}
