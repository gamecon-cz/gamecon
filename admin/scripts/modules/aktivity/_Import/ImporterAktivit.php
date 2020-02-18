<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
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

      ['error' => $singleProgramLineError] = $this->guardSingleProgramLineOnly($values);
      if ($singleProgramLineError) {
        $result['messages']['errors'][] = $singleProgramLineError;
        return $result;
      }

      $mainInstanceId = null;
      foreach ($values as $activityValues) {
        $idAktivity = $this->parseIdAktivity($activityValues);
        if ($idAktivity) {
          ['success' => $success, 'error' => $error] = $this->importExistingActivity($idAktivity, $activityValues);
          if (!empty($error)) {
            $result['messages']['errors'][] = $error;
            continue;
          }
          if (!empty($success)) {
            $result['messages']['notices'][] = $success;
          }
          $mainInstanceId = $activityValues[ExportAktivitSloupce::ID_AKTIVITY];
        } else {
          $mainInstanceId = $this->importNewActivity($mainInstanceId, $activityValues);
        }
      }
    } catch (\Google_Service_Exception $exception) {
      $result['messages']['errors'][] = 'Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.';
      $this->logovac->zaloguj($exception);
      return $result;
    }
    return $result;
  }

  private function parseIdAktivity(array $values): ?int {
    $id = trim($values[ExportAktivitSloupce::ID_AKTIVITY]);
    return $id
      ? (int)$id
      : null;
  }

  private function guardSingleProgramLineOnly(array $values): array {
    $programLines = [];
    foreach ($values as $row) {
      $programLine = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLine !== null && $programLine !== '' && !in_array($programLine, $programLines, true)) {
        $programLines[] = $programLine;
      }
    }
    if (count($programLines) > 1) {
      return [
        'success' => false,
        'error' => sprintf(
          'Importovat lze pouze jednu programovou linii. Importní soubor jich má %d: %s',
          count($programLines),
          implode(',', self::wrapByQuotes($programLines))
        ),
      ];
    }
    if (count($programLines) === 0) {
      return [
        'success' => false,
        'error' => 'Import musí určit programovou linii.',
      ];
    }
    return ['success' => true, 'error' => false];
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

  private function getIndexedValues(string $spreadsheetId): array {
    try {
      $values = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
    } catch (GoogleApiException $exception) {
      $this->logovac->zaloguj($exception);
      return ['success' => false, 'error' => 'Google Sheets API je dočasně nedostupné, zkuste to znovu za chvíli.'];
    }
    $cleanseValuesResult = $this->cleanseValues($values);
    ['success' => $cleansedValues, 'error' => $error] = $cleanseValuesResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
    $cleansedHeaderResult = $this->getCleansedHeader($cleansedValues);
    ['success' => $cleansedHeader, 'error' => $error] = $cleansedHeaderResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
    unset($cleansedValues[array_key_first($cleansedValues)]); // remove row with header

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
    $aktivita = \Aktivita::zId($id);
    if (!$aktivita) {
      return [
        'success' => false,
        'error' => sprintf("Aktivita s ID '%s' neexistuje. Nelze ji proto importem upravit.", $id),
      ];
    }
    if (!$aktivita->bezpecneEditovatelna()) {
      return [
        'success' => false,
        'error' => sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože je ve stavu ''%s", $aktivita->nazev(), $id, $aktivita->getStavNazev()),
      ];
    }
    return ['success' => 'Zatím nic s existující aktivitou ' . $id, 'error' => false];
  }

  private function importNewActivity(int $parentId, array $values): array {
    $aktivita = \Aktivita::zId($parentId);
    if (!$aktivita) {
      return [
        'success' => false,
        'error' => sprintf("Aktivita s ID '%s' neexistuje. Nelze ji proto importem upravit.", $parentId),
      ];
    }
    return ['success' => 'Zatím nic snovou aktivitou ' . $parentId, 'error' => false];
  }
}
