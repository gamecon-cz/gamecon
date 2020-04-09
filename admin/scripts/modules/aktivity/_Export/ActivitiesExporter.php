<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

use Gamecon\Admin\Modules\Aktivity\Export\Exceptions\ActivitiesExportException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Cas\DateTimeCz;

class ActivitiesExporter
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
  /**
   * @var string
   */
  private $baseUrl;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    string $baseUrl
  ) {
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->userId = $userId;
    $this->baseUrl = rtrim($baseUrl, '/');
  }

  /**
   * @param array|\Aktivita[] $aktivity
   * @param string $prefix
   * @return string Name of exported file
   */
  public function exportActivities(array $aktivity, string $prefix): string {
    $activitySheetTitle = $this->getActivitySheetTitle($aktivity);
    $spreadsheetTitle = $this->getSpreadsheetTitle($prefix, $activitySheetTitle);
    $spreadSheet = $this->createSheetForActivities($spreadsheetTitle, $activitySheetTitle);

    $activityData = $this->getActivityData($aktivity);
    $this->saveActivityData($activityData, $spreadSheet); // TODO export rooms, states

    $allTagsData = $this->getAllTagsData();
    $this->saveTagsData($allTagsData, $spreadSheet);

    $allStorytellersData = $this->getAllStorytellersData();
    $this->saveStorytellersData($allStorytellersData, $spreadSheet);

    $allRoomsData = $this->getAllRoomsData();
    $this->saveRoomsData($allRoomsData, $spreadSheet);

    $this->moveSpreadsheetToExportDir($spreadSheet);
    return $spreadsheetTitle;
  }

  /**
   * @param \Aktivita[] $aktivity
   * @return array
   */
  private function getActivityData(array $aktivity): array {
    $data[] = ExportAktivitSloupce::vsechnySloupce();
    foreach ($aktivity as $aktivita) {
      $zacatekDen = $aktivita->zacatek()
        ? $aktivita->zacatek()->format('l')
        : '';
      $konecDen = $aktivita->konec()
        ? $aktivita->konec()->format('l')
        : '';
      $zacatekCas = $aktivita->zacatek()
        ? $aktivita->zacatek()->format('G:i')
        : '';
      $zacatekCas = preg_replace('~:00$~', '', $zacatekCas);
      $konecCas = $aktivita->konec()
        ? $aktivita->konec()->format('G:i')
        : '';
      $konecCas = preg_replace('~:00$~', '', $konecCas);
      $endAtSameDayAtMidnight = $konecCas === '0' // midnight
        && $aktivita->zacatek()->modify('+1 day')->format('Ymd') === $aktivita->konec()->format('Ymd');
      if ($endAtSameDayAtMidnight) {
        $konecCas = '24'; // midnight
      }
      if ($aktivita->zacatek() && $aktivita->konec()) {
        $trvaniAktivity = $aktivita->konec()->getTimestamp() - $aktivita->zacatek()->getTimestamp();
        if ($trvaniAktivity > 60 * 60 * 24) {
          throw new ActivitiesExportException(
            "Aktivita by neměla začínat a končit v jiný den, nanejvýše o půlnoci: začátek '$zacatekDen':'$zacatekCas', konec '$konecDen':'$konecCas' u aktivity {$aktivita->id()} ({$aktivita->nazev()})"
          );
        }
      }
      $data[] = [
        $aktivita->id(), // ID aktivity
        $aktivita->typ()->nazev(), // Programová linie
        $aktivita->nazev(), // Název
        $aktivita->urlId(), // URL
        $aktivita->kratkyPopis(), // Krátká anotace
        implode('; ', $aktivita->tagy()), // Tagy
        $aktivita->getPopisRaw(), // Dlouhá anotace
        $zacatekDen, // Den
        $zacatekCas, // Začátek
        $konecCas, // Konec
        $aktivita->lokace()
          ? $aktivita->lokace()->nazev()
          : '', // Místnost
        implode('; ', $aktivita->orgLoginy()->getArrayCopy()), // Vypravěči
        $aktivita->getKapacitaUnisex(), // Kapacita unisex
        $aktivita->getKapacitaMuzu(), // Kapacita muži
        $aktivita->getKapacitaZen(), // Kapacita ženy
        $aktivita->tymova() // Je týmová
          ? 'ano'
          : 'ne',
        $aktivita->tymMinKapacita() ?? '', // Minimální kapacita týmu
        $aktivita->tymMaxKapacita() ?? '', // Maximální kapacita týmu
        (float)$aktivita->cenaZaklad(), // Cena
        $aktivita->bezSlevy() // Bez slev
          ? 'ano'
          : 'ne',
        (string)$aktivita->vybaveni(), // Vybavení
        $aktivita->stav()->nazev(), // Stav
        $aktivita->maObrazek()
          ? $aktivita->urlObrazku($this->baseUrl)
          : '', // Obrázek
      ];
    }
    return $data;
  }

  private function getAllTagsData(): array {
    $data[] = ExportTaguSloupce::vsechnySloupce();
    $tagy = \Tag::zVsech();
    foreach ($tagy as $tag) {
      $data[] = [
        $tag->id(),
        $tag->nazev(),
        $tag->poznamka(),
        $tag->katregorieTagu()->nazev(),
      ];
    }
    return $data;
  }

  private function getAllStorytellersData(): array {
    $data[] = ExportVypravecuSloupce::vsechnySloupce();
    $poradateleAktivit = \Uzivatel::poradateleAktivit();
    foreach ($poradateleAktivit as $poradatelAktivit) {
      $data[] = [
        $poradatelAktivit->id(),
        $poradatelAktivit->mail(),
        $poradatelAktivit->jmenoNick(),
      ];
    }
    return $data;
  }

  private function getAllRoomsData(): array {
    $data[] = ExportLokaciSloupce::vsechnySloupce();
    $lokace = \Lokace::zVsech();
    foreach ($lokace as $jednaLokace) {
      $data[] = [
        $jednaLokace->id(),
        $jednaLokace->nazev(),
        $jednaLokace->dvere(),
        $jednaLokace->poznamka(),
      ];
    }
    return $data;
  }

  private function saveActivityData(array $activityData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleSheetsService->setValuesInSpreadsheet($activityData, $spreadsheet->getSpreadsheetId(), 1);
  }

  private function saveTagsData(array $tagsData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleSheetsService->setValuesInSpreadsheet($tagsData, $spreadsheet->getSpreadsheetId(), 2);
  }

  private function saveStorytellersData(array $storytellersData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleSheetsService->setValuesInSpreadsheet($storytellersData, $spreadsheet->getSpreadsheetId(), 3);
  }

  private function saveRoomsData(array $roomsData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
    $this->googleSheetsService->setValuesInSpreadsheet($roomsData, $spreadsheet->getSpreadsheetId(), 4);
  }

  private function createSheetForActivities(string $sheetTitle, string $activitySheetTitle): \Google_Service_Sheets_Spreadsheet {
    $newSpreadsheet = $this->googleSheetsService->createNewSpreadsheet(
      $sheetTitle,
      [mb_ucfirst($activitySheetTitle), 'Tagy', 'Pořadatelé']
    );
    $sheets = $newSpreadsheet->getSheets();
    /** @var \Google_Service_Sheets_Sheet $sheet */
    foreach ($sheets as $sheet) {
      $this->googleSheetsService->setFirstRowAsHeader($newSpreadsheet->getSpreadsheetId(), $sheet->getProperties()->getSheetId());
    }
    return $newSpreadsheet;
  }

  private function getSpreadsheetTitle(string $prefix, string $baseTitle): string {
    return sprintf('%d %s - %s', $prefix, $baseTitle, (new DateTimeCz())->formatCasStandard());
  }

  private function getActivitySheetTitle(array $aktivity): string {
    $activitiesTypeNames = $this->getActivitiesUniqueTypeNames($aktivity);
    sort($activitiesTypeNames);
    return implode(' a ', $activitiesTypeNames);
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
    $wrappedRootExportDir = $this->googleDriveService->getLocalDirsReferencesByUserIdAndTag($this->userId, self::EXPORT_DIR_TAG);
    if ($wrappedRootExportDir) {
      $rootExportDir = reset($wrappedRootExportDir);
      $rootExportDirId = $rootExportDir->getGoogleDirId();
      if ($this->googleDriveService->dirByIdExists($rootExportDirId)) {
        return $rootExportDirId;
      }
      $this->googleDriveService->deleteLocalDirReferenceByDirId($rootExportDirId);
    }
    $createdDir = $this->createOrReuseDirForGameconExport();
    return $createdDir->getId();
  }

  private function createOrReuseDirForGameconExport(): \Google_Service_Drive_DriveFile {
    $exportDirName = self::EXPORT_DIR;
    $existingDirs = $this->googleDriveService->getDirsByName($exportDirName);
    $existingDir = reset($existingDirs);
    if ($existingDir) {
      $this->googleDriveService->saveDirReferenceLocally($existingDir, $this->userId, self::EXPORT_DIR_TAG);
      return $existingDir;
    }
    $createdDir = $this->googleDriveService->createDir($exportDirName);
    $this->googleDriveService->saveDirReferenceLocally($createdDir, $this->userId, self::EXPORT_DIR_TAG);
    return $createdDir;
  }
}
