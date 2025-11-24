<?php

use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Logger\JobResultLogger;

require_once __DIR__ . '/../../nastaveni/zavadec.php';

// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
/** @var Vyjimkovac $vyjimkovac */
$vyjimkovac->zobrazeni(Vyjimkovac::PLAIN);

$jobOutputLogger = new JobResultLogger();

/**
 * Výstup do logu
 */
function logs(
    $s,
    bool $zalogovatCas = true,
): void {
    global $jobOutputLogger;

    $jobOutputLogger->logs($s, $zalogovatCas);
}

/**
 * Výstup do logu
 */
function logsText(
    string $s,
): void {
    global $jobOutputLogger;

    $jobOutputLogger->writeMessage("<pre>$s</pre>");
}

function writeMessage(
    string $message,
    string $newLineAfter = "\n",
): void {
    global $jobOutputLogger;

    $jobOutputLogger->writeMessage($message . $newLineAfter);
}

if (defined('TESTING') && TESTING && !defined('MAILY_DO_SOUBORU')) {
    define('MAILY_DO_SOUBORU', true);
}
