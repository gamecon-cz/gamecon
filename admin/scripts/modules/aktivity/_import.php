<?php

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImporter;
use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportLogger;
use Gamecon\Mutex\Mutex;
use Gamecon\Role\Zidle;
use Gamecon\Vyjimkovac\Logovac;

/** @var \Gamecon\XTemplate\XTemplate $template */
/** @var \Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService $googleDriveService */
/** @var \Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService $googleSheetsService */
/** @var int $currentUserId */

$activitiesImportLogger = new ActivitiesImportLogger();
$now = new \DateTimeImmutable();
if (defined('TESTING') && TESTING && (int)$now->format('Y') !== (int)ROK) {
    $now = DateTimeImmutable::createFromFormat(\Gamecon\Cas\DateTimeCz::FORMAT_DB, GC_BEZI_OD);
}
$urlNaAktivity = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..';
$urlNaEditaciAktivity = $urlNaAktivity . '/upravy?aktivitaId=';

$importFormatHint = include __DIR__ . '/_export-import-hint.php';
$template->assign('importFormatHint', $importFormatHint);

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
        $urlNaEditaciAktivity,
        $now,
        URL_ADMIN . '/prava/' . Zidle::LETOSNI_VYPRAVEC,
        $vyjimkovac,
        Mutex::proAktivity(),
        URL_ADMIN . '/web/chyby',
        $activitiesImportLogger,
        new ExportAktivitSloupce(),
        new \Gamecon\Cas\DateTimeCz()
    );
    $vysledekImportuAktivit = $activitiesImporter->importActivities($googleSheetId);
    $importOznameni = include __DIR__ . '/_import-oznameni.php';
    $template->assign('importOznameni', $importOznameni($vysledekImportuAktivit));
    $template->parse('import.oznameni');
}

$spreadsheets = $googleSheetsService->getAllSpreadsheets();
['used' => $usedSpreadSheetIds, 'unused' => $unusedSpreadSheetIds] = $activitiesImportLogger->splitGoogleSheetIdsToUsedAndUnused(array_keys($spreadsheets));
foreach ($unusedSpreadSheetIds as $unusedSpreadSheetId) {
    $spreadsheet = $spreadsheets[$unusedSpreadSheetId];
    unset($spreadsheets[$unusedSpreadSheetId]);
    $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId(), ENT_QUOTES));
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
    $pouzitoKdy = $activitiesImportLogger->getImportedAt($usedSpreadSheetId, $now->getTimezone());
    $sheetsPouzityKdy[$usedSpreadSheetId] = $pouzitoKdy;
}
uasort($sheetsPouzityKdy, static function (?\DateTimeInterface $jedenSheetPouzitKdy, ?\DateTimeInterface $druhySheetPouzitKdy) {
    return $druhySheetPouzitKdy <=> $jedenSheetPouzitKdy;
});
foreach ($sheetsPouzityKdy as $usedSpreadSheetId => $sheetPouzitKdy) {
    $spreadsheet = $spreadsheets[$usedSpreadSheetId];
    $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId(), ENT_QUOTES));
    $template->assign('nazev', $spreadsheet->getName());
    $template->assign('url', $spreadsheet->getUrl());
    $template->assign('vytvorenoKdy', $spreadsheet->getCreatedAt()->relativni());
    $template->assign('upravenoKdy', $spreadsheet->getModifiedAt()->relativni());
    $template->assign('vytvorenoKdyPresne', $spreadsheet->getCreatedAt()->formatCasStandard());
    $template->assign('upravenoKdyPresne', $spreadsheet->getModifiedAt()->formatCasStandard());
    $template->assign('pouzitoKdy', $sheetPouzitKdy ? $sheetPouzitKdy->relativni() : '');
    $template->assign('pouzitoKdyPresne', $sheetPouzitKdy ? $sheetPouzitKdy->formatCasStandard() : '');
    $template->parse('import.spreadsheets.used.spreadsheet');
}
$template->parse('import.spreadsheets.used');

$template->parse('import.spreadsheets');

$template->parse('import');
$template->out('import');
