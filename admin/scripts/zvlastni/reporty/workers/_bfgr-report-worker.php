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
use Gamecon\BackgroundProcess\BackgroundProcessService;

// Zpracování argumentů z příkazové řádky
$options = getopt('', ['format:', 'recipientEmail:', 'recipientName::', 'includeNonPayers::', 'userId::']);

if (!isset($options['recipientEmail'])) {
  fwrite(STDERR, "Usage: bfgr-report-worker.php --recipientEmail=<email> [--recipientName=<name>] [--includeNonPayers] [--userId]\n");
  exit(1);
}

$userEmail = trim($options['recipientEmail']);
$userName = $options['recipientName'] ?? $userEmail;
$includeNonPayers = isset($options['includeNonPayers']);
$userId = $options['userId'] ?? null;

// Načtení aplikace
require_once __DIR__ . '/../../../../../nastaveni/zavadec.php';

$commandName = BackgroundProcessService::COMMAND_BFGR_REPORT;
$backgroundProcessService = BackgroundProcessService::vytvorZGlobals();

// Registruj shutdown funkci pro automatické označení dokončení
$backgroundProcessService->registerShutdownHandler($commandName);

try {
  ini_set('memory_limit', '512M');
  set_time_limit(600); // 10 minut pro background proces

  $systemoveNastaveni = SystemoveNastaveni::zGlobals();

  // Vygenerování reportu do dočasného souboru
  $tempFile = SPEC . '/bfgr-report-' . uniqid() . '.xlsx';
  $logFile = LOGY . '/bfgr-report-' . uniqid() . '.log';

  file_put_contents($logFile, date('Y-m-d H:i:s') . " Začínám generovat BFGR report pro uživatele {$userEmail}\n");

  $bfgrReport = new BfgrReport($systemoveNastaveni);
  $bfgrReport->exportuj(
      format: 'xlsx',
      vcetneStavuNeplatice: $includeNonPayers,
      doSouboru: $tempFile,
      idUzivatele: $userId,
  );

  file_put_contents($logFile, date('Y-m-d H:i:s') . " BFGR report vygenerován do souboru {$tempFile}\n", FILE_APPEND);

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
<p>BFGR report najdeš v příloze.</p>
<strong>Ročník:</strong> {$rocnik}<br>
<strong>Vygenerováno:</strong> {$timestamp}</p>
<p>S pozdravem,<br>
GameCon systém</p>
</body>
</html>
HTML;

  $gmail = GcMail::vytvorZGlobals()
                 ->adresat($userEmail)
                 ->predmet("BFGR Report - ročník {$rocnik}")
                 ->text($htmlBody)
                 ->prilohaSoubor($tempFile)
                 ->prilohaNazev($attachmentName);

  $odeslano = $gmail->odeslat(GcMail::FORMAT_HTML);

  if (jsmeNaOstre()) {
    // Smazání dočasného souboru
    @unlink($tempFile);
  }

  if ($odeslano) {

    file_put_contents($logFile, date('Y-m-d H:i:s') . " BFGR report odeslán na email {$userEmail}\n", FILE_APPEND);

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
      $e->getTraceAsString(),
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
