<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
use Gamecon\Cas\DateTimeGamecon;
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

  /**
   * @var array|\Aktivita[]|null[]
   */
  private $oldActivities = [];

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
      $activitiesValues = $this->getIndexedValues($spreadsheetId);

      ['error' => $singleProgramLineError] = $this->guardSingleProgramLineOnly($activitiesValues);
      if ($singleProgramLineError) {
        $result['messages']['errors'][] = $singleProgramLineError;
        return $result;
      }

      /** @var int|null $parentActivityId */
      $parentActivityId = null;
      foreach ($activitiesValues as $activityValues) {
        ['success' => $activityId, 'error' => $activityIdError] = $this->getActivityId($activityValues);
        if ($activityIdError) {
          $result['messages']['errors'][] = $activityIdError;
          continue;
        }
        if ($activityId) {
          ['success' => $importExistingActivitySuccess, 'error' => $importExistingActivityError] = $this->importExistingActivity($activityId, $activityValues);
          if ($importExistingActivityError) {
            $result['messages']['errors'][] = $importExistingActivityError;
            continue;
          }
          if ($importExistingActivitySuccess) {
            $result['messages']['notices'][] = $importExistingActivitySuccess;
          }
          $parentActivityId = $activityId;
        } else if ($parentActivityId && $this->mayBeInstance($parentActivityId, $activityValues)) {
          ['success' => $importInstanceSuccess, 'error' => $importInstanceError] = $this->importInstance($parentActivityId, $activityValues);
          if ($importInstanceError) {
            $result['messages']['errors'][] = $importInstanceError;
            continue;
          }
          if ($importInstanceSuccess) {
            $result['messages']['notices'][] = $importInstanceSuccess;
          }
        } else {
          ['success' => $importNewActivitySuccess, 'error' => $importNewActivityError] = $parentActivityId = $this->importNewActivity($activityValues);
          if ($importNewActivityError) {
            $result['messages']['errors'][] = $importNewActivityError;
            continue;
          }
          if ($importNewActivitySuccess) {
            $result['messages']['notices'][] = $importNewActivitySuccess;
          }
        }
      }
    } catch (\Google_Service_Exception $exception) {
      $result['messages']['errors'][] = 'Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.';
      $this->logovac->zaloguj($exception);
      return $result;
    }
    return $result;
  }

  private function importInstance(int $parentActivityId, array $values): array {
    return $this->success("TODO Zatím nic s instancí mateřské aktivity $parentActivityId: " . implode(';', $values));
  }

  private function success($success): array {
    return ['success' => $success, 'error' => false];
  }

  private function error(string $error): array {
    return ['success' => false, 'error' => $error];
  }

  private function mayBeInstance(int $parentActivityId, array $values): bool {
    /** @noinspection PhpUnhandledExceptionInspection */
    $parentActivity = $this->getOldActivityById($parentActivityId);

    $programovaLinie = $values[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if ($programovaLinie) {
      $programovaLinieId = (int)$programovaLinie; // type may be a name or an ID
      if ($programovaLinieId) {
        if ($programovaLinieId !== $parentActivity->typId()) {
          return false; // different type ID, this can not be an instance
        }
      } else if ($programovaLinie !== $parentActivity->typ()->nazev()) {
        return false; // different type name, this can not be an instance
      }
    }

    $nazev = $values[ExportAktivitSloupce::NAZEV] ?? null;
    if ($nazev && $nazev !== $parentActivity->nazev()) {
      return false; // different activity name, this can not be an instance
    }

    $url = $values[ExportAktivitSloupce::URL] ?? null;
    if ($url && $url === $parentActivity->urlId()) {
      return false; // different activity URL, this can not be an instance
    }

    return true; // it seems that this activity can be an instance of given parent activity
  }

  private function getOldActivityById(int $id): \Aktivita {
    $oldActivity = $this->findOldActivityById($id);
    if ($oldActivity) {
      return $oldActivity;
    }
    /** @noinspection PhpUnhandledExceptionInspection */
    throw new ImportAktivitException("Activity of ID '$id has not ben found");
  }

  private function findOldActivityById(int $id): ?\Aktivita {
    if (!array_key_exists($id, $this->oldActivities)) {
      $this->oldActivities[$id] = \Aktivita::zId($id);
    }
    return $this->oldActivities[$id];
  }

  private function getActivityId(array $activityValues): array {
    if ($activityValues[ExportAktivitSloupce::ID_AKTIVITY]) {
      return $this->success((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
    }
    $programovaLinie = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null; // may be name or ID
    $url = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if ($url && $programovaLinie) {
      $id = dbOneCol(<<<SQL
SELECT id_akce
FROM akce_seznam
INNER JOIN akce_typy ON akce_typy.id_typu = akce_seznam.typ
WHERE url_akce = $1 AND rok = $2 AND (akce_typy.typ_1pmn = $3 OR akce_typy.id_typu = $3)
SQL
        , [$url, $this->currentYear, $programovaLinie]
      );
      if ($id) {
        return ['success' => (int)$id, 'error' => false];
      }
    }
    if ($url) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2
SQL
        , [$url, $this->currentYear]
      );
      if (count($ids) === 1) { // without type there may be more IDs per URL-and-year
        return $this->success((int)current($ids));
      }
    }
    $nazevAkce = $activityValues[ExportAktivitSloupce::NAZEV];
    if ($nazevAkce) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2
SQL
        , [$nazevAkce, $this->currentYear]
      );
      if (count($ids) === 1) { // there may be more IDs per name-and-year
        return $this->success((int)current($ids));
      }
    }
    return $this->success(null);
  }

  private function getZacatek(array $values): array {
    if (empty($values[ExportAktivitSloupce::ZACATEK])) {
      return $this->success(null);
    }
    if (empty($values[ExportAktivitSloupce::DEN])) {
      return $this->error(sprintf('U aktivity %s je sice začátek (%s), ale chybí u ní den.', $this->describeActivityFromValues($values), $values[ExportAktivitSloupce::ZACATEK]));
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $values[ExportAktivitSloupce::DEN], $values[ExportAktivitSloupce::ZACATEK]);
  }

  private function getKonec(array $values): array {
    if (empty($values[ExportAktivitSloupce::KONEC])) {
      return $this->success(null);
    }
    if (empty($values[ExportAktivitSloupce::DEN])) {
      return $this->error(sprintf('U aktivity %s je sice konec (%s), ale chybí u ní den.', $this->describeActivityFromValues($values), $values[ExportAktivitSloupce::KONEC]));
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $values[ExportAktivitSloupce::DEN], $values[ExportAktivitSloupce::KONEC]);
  }

  private function describeActivityFromValues(array $values): string {
    $id = $values[ExportAktivitSloupce::ID_AKTIVITY] ?? '';
    $nazev = $values[ExportAktivitSloupce::NAZEV] ?? '';
    if ($id && $nazev) {
      return "$nazev ($id)";
    }
    $url = $values[ExportAktivitSloupce::URL] ?? '';
    if ($nazev && $url) {
      return "$nazev s URL '$url'";
    }
    if ($nazev) {
      return $nazev;
    }
    $kratkaAnotace = $values[ExportAktivitSloupce::KRATKA_ANOTACE] ?? '';
    return $kratkaAnotace ?: "'bez názvu'";
  }

  private function createDateTimeFromRangeBorder(int $year, string $dayName, string $hoursAndMinutes): array {
    try {
      $date = DateTimeGamecon::denKolemZacatkuGameconuProRok($dayName, $year);
    } catch (\Exception $exception) {
      return $this->error(sprintf("Nepodařilo se vytvořit datum z roku %d, dne '%s' a času '%s'. Chybný formát datumu. Detail: %s", $year, $dayName, $hoursAndMinutes, $exception->getMessage()));
    }

    if (!preg_match('~^(?<hours>\d+)\s*:\s*(?<minutes>\d*)$~', $hoursAndMinutes, $timeMatches)) {
      return $this->error(sprintf("Nepodařilo se nastavit čas u datumu z roku %d, dne '%s' a času '%s'. Chybný formát času.", $year, $dayName, $hoursAndMinutes));
    }
    $hours = (int)$timeMatches['hours'];
    $minutes = (int)($timeMatches['minutes'] ?? 0);
    $dateTime = $date->setTime($hours, $minutes, 0, 0);
    if (!$dateTime) {
      return $this->error(sprintf("Nepodařilo se nastavit čas u datumu z roku %d, dne '%s' a času '%s'. Chybný formát času.", $year, $dayName, $hoursAndMinutes));
    }
    return $this->success($dateTime);
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
      return $this->error(sprintf(
        'Importovat lze pouze jednu programovou linii. Importní soubor jich má %d: %s',
        count($programLines),
        implode(',', self::wrapByQuotes($programLines))
      ));
    }
    if (count($programLines) === 0) {
      return $this->error('Import musí určit programovou linii.');
    }
    return $this->success(true);
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
      return $this->error('Google Sheets API je dočasně nedostupné, zkuste to znovu za chvíli.');
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
      return $this->error(sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
        return "'$value'";
      }, $unknownColumns))));
    }
    if (count($cleansedHeader) === 0) {
      return $this->error('Chybí názvy sloupců v prvním řádku');
    }
    if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
      return $this->error(sprintf('Některé náxvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)));
    }
    return $this->success($cleansedHeader);
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
      return $this->error('Žádná data. Import je prázdný.');
    }
    return $this->success($cleansedValues);
  }

  private function importExistingActivity(int $id, array $values): array {
    $aktivita = $this->findOldActivityById($id);
    if (!$aktivita) {
      return $this->error(sprintf("Aktivita s ID '%s' neexistuje. Nelze ji proto importem upravit.", $id));
    }
    if (!$aktivita->bezpecneEditovatelna()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože je ve stavu '%s'", $aktivita->nazev(), $id, $aktivita->getStavNazev()));
    }
    if ($aktivita->zacatek() && $aktivita->zacatek()->getTimestamp() <= time()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože už začala (začátek v %s)", $aktivita->nazev(), $id, $aktivita->zacatek()->formatCasNaMinutyStandard()));
    }
    if ($aktivita->konec() && $aktivita->konec()->getTimestamp() <= time()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože už skončila (konec v %s)", $aktivita->nazev(), $id, $aktivita->konec()->formatCasNaMinutyStandard()));
    }
    return $this->success('TODO Zatím nic s existující aktivitou ' . $id);
  }

  private function importNewActivity(array $values): array {
    return $this->success('TODO Zatím nic snovou aktivitou ' . implode(';', $values));
  }
}
