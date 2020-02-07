<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleDirReference;

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
   * @param int $rok
   */
  public function exportAktivit(array $aktivity, int $rok) {
    $sheetTitle = $this->getSheetTitle($aktivity, $rok);
    $sheetBaseName = $this->getSheetBaseName($aktivity, $rok);
    $spreadSheet = $this->createSheetForActivities($sheetTitle, $sheetBaseName);
    $this->saveData([['header FOO'], ['content BAR']], $spreadSheet);
    $this->moveSpreadSheetToexportDir($spreadSheet);
  }

  private function createSheetForActivities(string $sheetTitle, string $tag): \Google_Service_Sheets_Spreadsheet {
    $newSpreadsheet = $this->googleSheetsService->createNewSpreadsheet($sheetTitle);
    $this->googleSheetsService->setFirstRowAsHeader($newSpreadsheet->getSpreadsheetId());
    $this->googleSheetsService->saveSpreadsheetReference($newSpreadsheet, $this->userId, $tag);
    return $newSpreadsheet;
  }

  private function getSheetBaseName(array $aktivity, int $rok): string {
    $activitiesTypeNames = $this->getActivitiesTypeNames($aktivity);
    sort($activitiesTypeNames);
    return sprintf('%d_%s', $rok, implode('-', $activitiesTypeNames));
  }

  private function getSheetTitle(array $aktivity, int $rok): string {
    $activitiesTypeNames = $this->getActivitiesTypeNames($aktivity);
    sort($activitiesTypeNames);
    return sprintf('%s (%d)', implode(' a ', $activitiesTypeNames), $rok);
  }

  /**
   * @param array $aktivity
   * @return array|string[]
   */
  private function getActivitiesTypeNames(array $aktivity): array {
    return array_map(
      static function (\Aktivita $aktivita) {
        return $aktivita->typ();
      },
      $aktivity
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
