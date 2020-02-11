<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;

class ExporterAktivit
{

  private const EXPORT_DIR = '/admin.gamecon.cz/aktivity';
  private const EXPORT_DIR_TAG = 'root-export-dir';

  /**
   * @var GoogleDriveService
   */
  private $googleDriveService;
  /**
   * @var GoogleSheetsService
   */
  private $googleSheetsService;
  /**
   * @var int
   */
  private $userId;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService
  ) {
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->userId = $userId;
  }

  /**
   * @param array|\Aktivita[] $aktivity
   * @param string $prefix
   * @return string Name of exported file
   */
  public function exportujAktivity(array $aktivity, string $prefix): string {
    $sheetTitle = $this->getSheetTitle($aktivity, $prefix);
    $spreadSheet = $this->createSheetForActivities($sheetTitle);
    $data[] = [
      'ID aktivity',
      'Programová linie',
      'Název',
      'URL',
      'Krátká anotace',
      'Dlouhá anotace',
      'Tagy',
      'Začátek',
      'Konec',
      'Místnost',
      'Vypravěči',
      'Kapacita unisex',
      'Kapacita muži',
      'Kapacita ženy',
      'Je týmová',
      'Minimální kapacita týmu',
      'Maximální kapacita týmu',
      'Cena',
      'Bez slev',
      'Vybavení',
      'Stav',
    ];
    foreach ($aktivity as $aktivita) {
      $data[] = [
        $aktivita->id(),
        $aktivita->typ()->nazev(), // Programová linie
        $aktivita->nazev(),
        $aktivita->urlId(),
        $aktivita->kratkyPopis(),
        $aktivita->popis(),
        implode(',', $aktivita->tagy()),
        $aktivita->zacatek()->formatCasNaMinutyStandard(),
        $aktivita->konec()->formatCasNaMinutyStandard(),
        (string)$aktivita->lokace(),
        implode(',', $aktivita->getOrganizatoriIds()),
        $aktivita->getKapacitaUnisex(),
        $aktivita->getKapacitaMuzu(),
        $aktivita->getKapacitaZen(),
        $aktivita->tymova()
          ? 'ano'
          : 'ne',
        $aktivita->tymMinKapacita() ?? '',
        $aktivita->tymMaxKapacita() ?? '',
        (float)$aktivita->cenaZaklad(),
        $aktivita->bezSlevy()
          ? 'ano'
          : 'ne',
        (string)$aktivita->vybaveni(),
        $aktivita->getStavNazev(),
      ];
    }
    $this->saveData($data, $spreadSheet);
    $this->moveSpreadsheetToExportDir($spreadSheet);
    return $sheetTitle;
  }

  private function saveData(array $values, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleSheetsService->setValuesInSpreadsheet($values, $spreadsheet->getSpreadsheetId());
  }

  private function createSheetForActivities(string $sheetTitle): \Google_Service_Sheets_Spreadsheet {
    $newSpreadsheet = $this->googleSheetsService->createNewSpreadsheet($sheetTitle);
    $this->googleSheetsService->setFirstRowAsHeader($newSpreadsheet->getSpreadsheetId());
    $this->googleSheetsService->saveSpreadsheetReference($newSpreadsheet, $this->userId);
    return $newSpreadsheet;
  }

  private function getSheetTitle(array $aktivity, string $prefix): string {
    $activitiesTypeNames = $this->getActivitiesUniqueTypeNames($aktivity);
    sort($activitiesTypeNames);
    return sprintf('%d %s - %s', $prefix, implode(' a ', $activitiesTypeNames), date('j. n. Y H:i:s'));
  }

  /**
   * @param array $aktivity
   * @return array|string[]
   */
  private function getActivitiesUniqueTypeNames(array $aktivity): array {
    return array_unique(
      array_map(
        static function (\Aktivita $aktivita) {
          return $aktivita->typ()->nazev();
        },
        $aktivity
      )
    );
  }

  private function moveSpreadsheetToExportDir(\Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleDriveService->moveFileToDir($spreadsheet->getSpreadsheetId(), $this->getExportDirId());
  }

  private function getExportDirId(): string {
    $wrappedRootExportDir = $this->googleDriveService->getDirsReferencesByUserIdAndTag($this->userId, self::EXPORT_DIR_TAG);
    if ($wrappedRootExportDir) {
      $rootExportDir = reset($wrappedRootExportDir);
      $rootExportDirId = $rootExportDir->getGoogleDirId();
      if ($this->googleDriveService->dirByIdExists($rootExportDirId)) {
        return $rootExportDirId;
      }
      $this->googleDriveService->deleteDirReferenceByDirId($rootExportDirId);
    }
    $createdDir = $this->createDirForGameconExport();
    return $createdDir->getId();
  }

  private function createDirForGameconExport(): \Google_Service_Drive_DriveFile {
    $exportDirName = self::EXPORT_DIR;
    if ($this->googleDriveService->dirExists($exportDirName)) {
      $exportDirName = uniqid($exportDirName, true);
    }
    $createdDir = $this->googleDriveService->createDir($exportDirName);
    $this->googleDriveService->saveDirReference($createdDir, $this->userId, self::EXPORT_DIR_TAG);
    return $createdDir;
  }
}
