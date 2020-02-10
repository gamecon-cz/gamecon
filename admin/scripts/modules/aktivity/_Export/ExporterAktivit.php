<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;

class ExporterAktivit
{

  private const EXPORT_DIR = '/gamecon.cz/admin/aktivity/export';
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
    $this->saveData([['header FOO'], ['content BAR']], $spreadSheet);
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
    return sprintf('%d %s - %s', $prefix, implode(' a ', $activitiesTypeNames), date('j. n. Y H:m:s'));
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
      if ($this->googleDriveService->folderByIdExists($rootExportDirId)) {
        return $rootExportDirId;
      }
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
