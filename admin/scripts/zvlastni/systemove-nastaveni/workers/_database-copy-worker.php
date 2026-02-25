#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker pro kopírování databáze z ostré na pozadí
 *
 * Použití:
 *   php _database-copy-worker.php
 */

// Zkusíme načíst zdrojovou DB a backupFile z CLI argumentů, nejdřív standardním getopt, pak fallbackem na $argv
$options = getopt('', ['sourceDb::', 'backupFile::']);
$zdrojovaDb = $options['sourceDb'] ?? null;
$backupFile = $options['backupFile'] ?? null;
if (isset($argv)) {
    foreach ($argv as $arg) {
        if (!$zdrojovaDb && str_starts_with($arg, '--sourceDb=')) {
            $zdrojovaDb = substr($arg, strlen('--sourceDb='));
        }
        if (!$backupFile && str_starts_with($arg, '--backupFile=')) {
            $backupFile = substr($arg, strlen('--backupFile='));
        }
    }
}

// Před načtením konfigurace můžeme vynutit ročník podle zdrojové DB nebo názvu souboru zálohy
if (!defined('ROCNIK')) {
    $vynucenyRocnik = (int)date('Y'); // default
    if ($backupFile && preg_match('~export_(\d{4})-~', basename($backupFile), $m)) {
        $vynucenyRocnik = (int)$m[1];
    } elseif ($backupFile && preg_match('~/(\d{4})/backup/db/~', $backupFile, $m)) {
        $vynucenyRocnik = (int)$m[1];
    } elseif (preg_match('~gamecon_(\d{4})~', $zdrojovaDb ?? '', $m)) {
        $vynucenyRocnik = (int)$m[1];
    }
    define('ROCNIK', $vynucenyRocnik);
}

require_once __DIR__ . '/../../../../../nastaveni/zavadec.php';

use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\SystemoveNastaveni\KopieOstreDatabaze;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

// Nastavení pro dlouhotrvající proces
ini_set('memory_limit', '512M');
set_time_limit(600); // 10 minut

$commandName = BackgroundProcessService::COMMAND_DB_COPY;
$backgroundProcessService = BackgroundProcessService::vytvorZGlobals();

// Registruj shutdown funkci pro automatické označení dokončení
$backgroundProcessService->registerShutdownHandler($commandName);

$logFile = LOGY . '/database-copy-' . date('Y-m-d_H-i-s') . '.log';
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
    logMessage("Začátek kopírování databáze z ostré");

    // uložit ročník pro další requesty na betě
    if (isset($vynucenyRocnik)) {
        $rocnikOverrideFile = __DIR__ . '/../../../../../cache/private/rocnik_override';
        @mkdir(dirname($rocnikOverrideFile), 0775, true);
        file_put_contents($rocnikOverrideFile, (string)$vynucenyRocnik);
    }

    $kopieOstreDatabaze = KopieOstreDatabaze::createFromGlobals();

    if ($backupFile) {
        logMessage("Kopíruji ze souboru zálohy '{$backupFile}' (ROCNIK=" . ROCNIK . ")");
        $kopieOstreDatabaze->zkopirujZeSouboruZalohy($backupFile);
    } else {
        $nastaveniOstre = SystemoveNastaveni::zGlobals()->prihlasovaciUdajeOstreDatabaze();
        $kopirovanaDb = $zdrojovaDb ?? $nastaveniOstre['DB_NAME'];
        logMessage("Kopíruji databázi '{$kopirovanaDb}' (ROCNIK=" . ROCNIK . ")");
        $kopieOstreDatabaze->zkopirujDatabazi($kopirovanaDb);
    }

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    logMessage("Kopírování databáze dokončeno úspěšně za $duration sekund");

    exit(0);

} catch (\Throwable $e) {
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    $errorMessage = sprintf(
        "Chyba při kopírování databáze: %s\nSoubor: %s:%d\nTrvání: %s s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $duration
    );

    logMessage($errorMessage);
    logMessage("Stack trace:\n" . $e->getTraceAsString());

    $backgroundProcessService->markProcessCompleted($commandName, false, $e->getMessage());

    exit(1);
}
