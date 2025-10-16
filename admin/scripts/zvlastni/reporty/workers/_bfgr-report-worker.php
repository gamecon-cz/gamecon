#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Background worker pro generování BFGR reportu
 * Spouští se asynchronně a výsledek odesílá emailem
 */

use Gamecon\Report\BfgrReport;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

// Zpracování argumentů z příkazové řádky
$options = getopt('', ['format:', 'userEmail:', 'userName::', 'includeNonPayers::']);

if (!isset($options['userEmail'])) {
    fwrite(STDERR, "Usage: bfgr-report-worker.php --userEmail=<email> [--userName=<name>] [--includeNonPayers]\n");
    exit(1);
}

$userEmail = $options['userEmail'];
$userName = $options['userName'] ?? $userEmail;
$includeNonPayers = isset($options['includeNonPayers']);

// Načtení aplikace
require_once __DIR__ . '/../../../../../nastaveni/zavadec.php';

try {
    ini_set('memory_limit', '512M');
    set_time_limit(600); // 10 minut pro background proces

    $systemoveNastaveni = SystemoveNastaveni::zGlobals();

    // Vygenerování reportu do dočasného souboru
    $tempFile = SPEC . '/bfgr-report-' . uniqid() . '.xlsx';

    $bfgrReport = new BfgrReport($systemoveNastaveni);
    $bfgrReport->exportuj(
        format: 'xlsx',
        vcetneStavuNeplatice: $includeNonPayers,
        doSouboru: $tempFile,
    );

    // Kontrola, zda byl soubor vytvořen a není prázdný
    if (!file_exists($tempFile) || filesize($tempFile) === 0) {
        throw new RuntimeException('Report nebyl vygenerován nebo je prázdný');
    }

    // Příprava emailu s přílohou
    $rocnik = $systemoveNastaveni->rocnik();
    $timestamp = date('Y-m-d_H-i-s');
    $attachmentName = "bfgr-report-{$rocnik}-{$timestamp}.xlsx";

    $htmlBody = <<<HTML
<html lang="cs">
<body>
<p>Ahoj {$userName},</p>
<p>BFGR report byl úspěšně vygenerován a je připojen k tomuto emailu.</p>
<strong>Ročník:</strong> {$rocnik}<br>
<strong>Vygenerováno:</strong> {$timestamp}</p>
<p>S pozdravem,<br>
GameCon systém</p>
</body>
</html>
HTML;

    $mail = GcMail::vytvorZGlobals()
        ->adresat($userEmail)
        ->predmet("BFGR Report - ročník {$rocnik}")
        ->text($htmlBody)
        ->prilohaSoubor($tempFile)
        ->prilohaNazev($attachmentName);

    $odeslano = $mail->odeslat(GcMail::FORMAT_HTML);

    // Smazání dočasného souboru
    @unlink($tempFile);

    if ($odeslano) {
        echo "Report byl úspěšně odeslán na {$userEmail}\n";
        exit(0);
    } else {
        throw new RuntimeException('Nepodařilo se odeslat email');
    }

} catch (Throwable $e) {
    // Logování chyby
    $errorMessage = sprintf(
        "[%s] Chyba při generování BFGR reportu pro uživatele %s: %s\n%s\n",
        date('Y-m-d H:i:s'),
        $userEmail,
        $e->getMessage(),
        $e->getTraceAsString()
    );

    $logFile = LOGY . '/bfgr-report-errors.log';
    @file_put_contents($logFile, $errorMessage, FILE_APPEND);

    // Odeslání chybového emailu uživateli
    try {
        $errorHtml = <<<HTML
<html lang="cs">
<body>
<p>Ahoj {$userName},</p>
<p>Bohužel došlo k chybě při generování BFGR reportu.</p>
<p><strong>Chyba:</strong> {$e->getMessage()}</p>
<p>Kontaktuj prosím administrátora systému.</p>
<p>S pozdravem,<br>
GameCon systém</p>
</body>
</html>
HTML;

        GcMail::vytvorZGlobals()
            ->adresat($userEmail)
            ->predmet("Chyba při generování BFGR reportu")
            ->text($errorHtml)
            ->odeslat(GcMail::FORMAT_HTML);
    } catch (Throwable $mailError) {
        // Nemůžeme ani odeslat chybový email
        fwrite(STDERR, "Nepodařilo se odeslat chybový email: {$mailError->getMessage()}\n");
    }

    fwrite(STDERR, $errorMessage);
    exit(1);
}
