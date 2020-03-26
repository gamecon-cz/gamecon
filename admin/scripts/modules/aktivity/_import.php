<?php

use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImporter;
use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportLogger;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;
use Gamecon\Zidle;

$activitiesImportLogger = new ActivitiesImportLogger();
$ted = new \DateTimeImmutable();
$urlNaAktivity = $_SERVER['REQUEST_URI'] . '/..';
$urlNaEditaciAktivity = $urlNaAktivity . '/upravy?aktivitaId=';
$baseUrl = (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

if (!empty($_POST['googleSheetId'])) {
  $googleSheetId = $_POST['googleSheetId'];
  if ($activitiesImportLogger->wasImported($googleSheetId)) {
    chyba(sprintf("Export '%s' už byl použit. Zkus jiný.", $googleDriveService->getFileName($googleSheetId)));
    exit;
  }
  /** @var Logovac $vyjimkovac */
  $activitiesImporter = new ActivitiesImporter(
    $currentUserId,
    $googleDriveService,
    $googleSheetsService,
    ROK,
    $urlNaEditaciAktivity,
    $ted,
    $baseUrl . '/admin/prava/' . Zidle::VYPRAVEC,
    $vyjimkovac,
    $baseUrl,
    Mutex::proAktivity(),
    $baseUrl . '/admin/web/chyby',
    $activitiesImportLogger
  );
  $vysledekImportuAktivit = $activitiesImporter->importActivities($googleSheetId);
  $naimportovanoPocet = $vysledekImportuAktivit->getImportedCount();
  $nazevImportovanehoSouboru = $vysledekImportuAktivit->getProcessedFilename();
  $successMessages = $vysledekImportuAktivit->getSuccessMessages();
  $warningMessages = $vysledekImportuAktivit->getWarningMessages();
  $errorMessages = $vysledekImportuAktivit->getErrorMessages();

  $zprava = sprintf("Bylo naimportováno %d aktivit z Google sheet '%s'", $naimportovanoPocet, $nazevImportovanehoSouboru);
  if ($naimportovanoPocet > 0) {
    oznameni($zprava, false);
  } else {
    chyba($zprava, false);
  }
  $oznameni = \Chyba::vyzvedniHtml();
  $template->assign('oznameni', $oznameni);
  $template->parse('import.oznameni');

  if ($errorMessages) {
    foreach ($errorMessages as $errorMessage) {
      $template->assign('error', $errorMessage);
      $template->parse('import.errors.error');
    }
    $template->parse('import.errors');
  }
  if ($warningMessages) {
    foreach ($warningMessages as $warningMessage) {
      $template->assign('warning', $warningMessage);
      $template->parse('import.warnings.warning');
    }
    $template->parse('import.warnings');
  }
  if ($successMessages) {
    foreach ($successMessages as $successMessage) {
      $template->assign('success', $successMessage);
      $template->parse('import.successes.success');
    }
    $template->parse('import.successes');
  }
}

$spreadsheets = $googleSheetsService->getAllSpreadsheets();
['used' => $usedSpreadSheetIds, 'unused' => $unusedSpreadSheetIds] = $activitiesImportLogger->splitGoogleSheetIdsToUsedAndUnused(array_keys($spreadsheets));
foreach ($unusedSpreadSheetIds as $unusedSpreadSheetId) {
  $spreadsheet = $spreadsheets[$unusedSpreadSheetId];
  unset($spreadsheets[$unusedSpreadSheetId]);
  $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId()));
  $template->assign('nazev', $spreadsheet->getName());
  $template->assign('url', $spreadsheet->getUrl());
  $template->assign('vytvorenoKdy', $spreadsheet->getCreatedAt()->relativni());
  $template->assign('upravenoKdy', $spreadsheet->getModifiedAt()->relativni());
  $template->assign('vytvorenoKdyPresne', $spreadsheet->getCreatedAt()->formatCasStandard());
  $template->assign('upravenoKdyPresne', $spreadsheet->getModifiedAt()->formatCasStandard());
  $template->parse('import.spreadsheets.unused.spreadsheet');
}
$template->parse('import.spreadsheets.unused');

$sheetsPouzityKdy = [];
foreach ($usedSpreadSheetIds as $usedSpreadSheetId) {
  $pouzitoKdy = $activitiesImportLogger->getImportedAt($usedSpreadSheetId, $ted->getTimezone());
  $sheetsPouzityKdy[$usedSpreadSheetId] = $pouzitoKdy;
}
uasort($sheetsPouzityKdy, static function (\DateTimeInterface $jedenSheetPouzitKdy, \DateTimeInterface $druhySheetPouzitKdy) {
  return $druhySheetPouzitKdy <=> $jedenSheetPouzitKdy;
});
foreach ($sheetsPouzityKdy as $usedSpreadSheetId => $sheetPouzitKdy) {
  $spreadsheet = $spreadsheets[$usedSpreadSheetId];
  $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId()));
  $template->assign('nazev', $spreadsheet->getName());
  $template->assign('url', $spreadsheet->getUrl());
  $template->assign('vytvorenoKdy', $spreadsheet->getCreatedAt()->relativni());
  $template->assign('upravenoKdy', $spreadsheet->getModifiedAt()->relativni());
  $template->assign('vytvorenoKdyPresne', $spreadsheet->getCreatedAt()->formatCasStandard());
  $template->assign('upravenoKdyPresne', $spreadsheet->getModifiedAt()->formatCasStandard());
  $template->assign('pouzitoKdy', $sheetPouzitKdy->relativni());
  $template->assign('pouzitoKdyPresne', $sheetPouzitKdy->formatCasStandard());
  $template->parse('import.spreadsheets.used.spreadsheet');
}
$template->parse('import.spreadsheets.used');

$template->parse('import.spreadsheets');

$template->parse('import');
$template->out('import');
