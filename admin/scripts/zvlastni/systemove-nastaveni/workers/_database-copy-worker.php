#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker pro kopírování databáze z ostré na pozadí
 *
 * Použití:
 *   php _database-copy-worker.php
 */

// Zkusíme načíst zdrojovou DB z CLI argumentů, nejdřív standardním getopt, pak fallbackem na $argv
$options = getopt('', ['sourceDb::']);
$zdrojovaDb = $options['sourceDb'] ?? null;
if (!$zdrojovaDb && isset($argv)) {
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--sourceDb=')) {
            $zdrojovaDb = substr($arg, strlen('--sourceDb='));
            break;
        }
    }
}

// Před načtením konfigurace můžeme vynutit ročník podle zdrojové DB
if (!defined('ROCNIK')) {
    $vynucenyRocnik = $zdrojovaDb === 'gamecon_2024'
        ? 2024
        : 2025;
    define('ROCNIK', $vynucenyRocnik);
}
$rocnikOverrideFile = __DIR__ . '/../../../../../cache/private/rocnik_override';

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
        @mkdir(dirname($rocnikOverrideFile), 0775, true);
        file_put_contents($rocnikOverrideFile, (string)$vynucenyRocnik);
    }

    $kopieOstreDatabaze = KopieOstreDatabaze::createFromGlobals();
    $nastaveniOstre = SystemoveNastaveni::zGlobals()->prihlasovaciUdajeOstreDatabaze();
    $kopirovanaDb = $zdrojovaDb ?? $nastaveniOstre['DB_NAME'];
    logMessage("Kopíruji databázi '{$kopirovanaDb}' (ROCNIK=" . ROCNIK . ")");
    $kopieOstreDatabaze->zkopirujDatabazi($kopirovanaDb);

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

    exit(1);
}
