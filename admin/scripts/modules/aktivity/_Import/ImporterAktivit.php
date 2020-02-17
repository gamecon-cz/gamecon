<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Vyjimkovac\Logovac;

class ImporterAktivit
{

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
   * @var int
   */
  private $currentYear;
  /**
   * @var Logovac
   */
  private $logovac;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    int $currentYear,
    Logovac $logovac
  ) {
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->userId = $userId;
    $this->currentYear = $currentYear;
    $this->logovac = $logovac;
  }

  public function importujAktivity(string $spreadsheetId): array {
    $result = [
      'importedCount' => 0,
      'processedFileName' => null,
      'messages' => [
        'notices' => [],
        'warnings' => [],
        'errors' => [],
      ],
    ];
    try {
      $result['processedFileName'] = $this->googleDriveService->getFileName($spreadsheetId);
      $values = $this->getIndexedValues($spreadsheetId);
      $this->guardSingleActivityTypeOnly($values);
      $mainInstanceId = null;
      foreach ($values as $activityValues) {
        $idAktivity = $this->parseIdAktivity($activityValues);
        if ($idAktivity) {
          ['success' => $success, 'error' => $error] = $this->importExistingActivity($idAktivity, $activityValues);
          if (!empty($error)) {
            $result['messages']['errors'][] = $error;
            continue;
          }
          $mainInstanceId = $activityValues[ExportAktivitSloupce::ID_AKTIVITY];
        } else {
          $mainInstanceId = $this->importNewActivity($activityValues, $mainInstanceId);
        }
      }
    } catch (\Google_Service_Exception $exception) {
      $result['messages']['errors'][] = 'Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.';
      $this->logovac->zaloguj($exception);
      return $result;
    }
    return $result;
  }

  private function getIndexedValues(string $spreadsheetId): array {
    $values = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
    $lastResult = $this->cleanseValues($values);
    ['success' => $cleansedValues, 'error' => $error] = $lastResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
    $lastResult = $this->getCleansedHeader($cleansedValues);
    ['success' => $cleansedHeader, 'error' => $error] = $lastResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
    unset($cleansedValues[array_key_first($cleansedValues)]);
    $indexedValues = [];
    $positionsOfValuesWithoutHeaders = [];
    foreach ($cleansedValues as $cleansedRow) {
      $indexedRow = [];
      foreach ($cleansedRow as $columnIndex => $cleansedValue) {
        $columnName = $cleansedHeader[$columnIndex] ?? false;
        if ($columnName) {
          $indexedRow[$columnName] = $cleansedValue;
        } else if ($cleansedValue !== '') {
          $positionsOfValuesWithoutHeaders[$columnIndex] = $columnIndex + 1;
        }
      }
      if (count($positionsOfValuesWithoutHeaders) > 0) {
        return [
          'success' => false,
          'error' => sprintf('Některým sloupcům chybí název a to na pozicích %s', implode(',', $positionsOfValuesWithoutHeaders)),
        ];
      }
      $indexedValues[] = $indexedRow;
    }
    return $indexedValues;
  }

  private function getCleansedHeader(array $values): array {
    $cleanse = static function (string $value) {
      strtolower(odstranDiakritiku($value));
    };
    $cleansedKnownColumns = array_map($cleanse, ExportAktivitSloupce::getVsechnySloupce());
    $header = reset($values);
    $cleansedHeader = [];
    $unknownColumns = [];
    $emptyColumnsPositions = [];
    foreach ($header as $index => $value) {
      $cleansedValue = $cleanse($value);
      if (in_array($cleansedValue, $cleansedKnownColumns, true)) {
        $cleansedHeader[$index] = $cleansedValue;
      } else if ($value === '') {
        $emptyColumnsPositions[$index] = $index + 1;
      } else {
        $unknownColumns[] = $value;
      }
    }
    if (count($unknownColumns) > 0) {
      return [
        'error' => sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
          return "'$value'";
        }, $unknownColumns))),
        'success' => false,
      ];
    }
    if (count($cleansedHeader) === 0) {
      return [
        'error' => 'Chybí názvy sloupců v prvním řádku',
        'success' => false,
      ];
    }
    if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
      return [
        'error' => sprintf('Některé náxvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)),
        'success' => false,
      ];
    }
    return [
      'success' => $cleansedHeader,
      'error' => false,
    ];
  }

  private function cleanseValues(array $values): array {
    $cleansedValues = [];
    foreach ($values as $row) {
      $cleansedRow = [];
      $rowIsEmpty = true;
      foreach ($row as $value) {
        $cleansedValue = trim($value);
        $cleansedRow[] = $cleansedValue;
        $rowIsEmpty = $rowIsEmpty && $cleansedValue === '';
      }
      if (!$rowIsEmpty) {
        $cleansedValues[] = $cleansedRow;
      }
    }
    if (count($cleansedValues) === 0) {
      return [
        'success' => false,
        'error' => 'Žádná data. Import je prázdný.',
      ];
    }
    return [
      'success' => $cleansedValues,
      'error' => false,
    ];
  }

  private function importExistingActivity(int $id, array $values): array {
    $result = ['success' => false, 'error' => null];
    $aktivita = \Aktivita::zId($id);
    if (!$aktivita) {
      $result['error'] = sprintf("Aktivita s ID '%s' neexistuje. Nelze ji proto importem upravit.", $id);
      return $result;
    }
    $result['success'] = 'Zatím nic TODO';
    return $result;
  }

  private function parseIdAktivity(array $values): ?int {
    $id = trim($values[ExportAktivitSloupce::ID_AKTIVITY]);
    return $id
      ? (int)$id
      : null;
  }
}
