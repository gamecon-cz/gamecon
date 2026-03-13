#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker pro generování statických JSON souborů programu
 *
 * Použití:
 *   php _program-static-files-worker.php --rocnik=2026
 */

$options = getopt('', ['rocnik::']);
$rocnik = isset($options['rocnik']) ? (int)$options['rocnik'] : null;
if (isset($argv)) {
    foreach ($argv as $arg) {
        if (!$rocnik && str_starts_with($arg, '--rocnik=')) {
            $rocnik = (int)substr($arg, strlen('--rocnik='));
        }
    }
}

require_once __DIR__ . '/../../../../nastaveni/zavadec.php';

use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

ini_set('memory_limit', '512M');
set_time_limit(120); // 2 minuty by měly stačit

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

$rocnik = $rocnik ?: $systemoveNastaveni->rocnik();

$commandName = BackgroundProcessService::COMMAND_PROGRAM_STATIC_FILES;
$backgroundProcessService = BackgroundProcessService::vytvorZGlobals();

$backgroundProcessService->registerShutdownHandler($commandName);

$logFile = LOGY . '/program-static-files-' . date('Y-m-d_H-i-s') . '.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

try {
    $generator = new ProgramStaticFileGenerator($systemoveNastaveni);

    logMessage("Start worker pro rocnik $rocnik");

    $iteration = 0;
    $maxIterations = 20; // pojistka proti nekonečné smyčce

    while ($iteration < $maxIterations) {
        $iteration++;

        $dirtyObsazenosti = $generator->hasDirtyFlag(ProgramStaticFileType::OBSAZENOSTI);
        $dirtyAktivity = $generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY);
        $dirtyPopisy = $generator->hasDirtyFlag(ProgramStaticFileType::POPISY);

        if (!$dirtyObsazenosti && !$dirtyAktivity && !$dirtyPopisy) {
            logMessage("Žádné dirty flagy, ukončuji po $iteration iteracích");
            break;
        }

        logMessage("Iterace $iteration: dirty aktivity=$dirtyAktivity, popisy=$dirtyPopisy, obsazenosti=$dirtyObsazenosti");

        // Smazat flagy PŘED regenerací — nové změny během regenerace vytvoří nové flagy
        if ($dirtyObsazenosti) {
            $generator->deleteDirtyFlag(ProgramStaticFileType::OBSAZENOSTI);
        }
        if ($dirtyAktivity) {
            $generator->deleteDirtyFlag(ProgramStaticFileType::AKTIVITY);
        }
        if ($dirtyPopisy) {
            $generator->deleteDirtyFlag(ProgramStaticFileType::POPISY);
        }

        if ($dirtyAktivity) {
            $file = $generator->generateAktivity($rocnik);
            logMessage("Vygenerováno: $file");
        }
        if ($dirtyPopisy) {
            $file = $generator->generatePopisy($rocnik);
            logMessage("Vygenerováno: $file");
        }
        if ($dirtyObsazenosti) {
            $file = $generator->generateObsazenosti($rocnik);
            logMessage("Vygenerováno: $file");
        }

        $generator->updateManifest($rocnik);
        logMessage("Manifest aktualizován");
    }

    $generator->cleanup($rocnik);
    logMessage("Cleanup dokončen");

    exit(0);

} catch (\Throwable $e) {
    $errorMessage = sprintf(
        "Chyba při generování statických souborů programu: %s\nSoubor: %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
    );

    logMessage($errorMessage);
    logMessage("Stack trace:\n" . $e->getTraceAsString());

    $backgroundProcessService->markProcessCompleted($commandName, false, $e->getMessage());

    exit(1);
}
