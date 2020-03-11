<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\DuplicatedUnifiedKeyException;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Mutex\Mutex;
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
   * @var \DateTimeInterface
   */
  private $now;
  /**
   * @var Logovac
   */
  private $logovac;
  /**
   * @var array|\Aktivita[]|null[]
   */
  private $oldActivities = [];
  /**
   * @var array|\Typ[][]
   */
  private $programLinesCache;
  /**
   * @var array|\Typ[][]
   */
  private $programLocationsCache;
  /**
   * @var array|\Tag[][]
   */
  private $tagsCache;
  /**
   * @var array|\Stav[][]
   */
  private $StatesCache;
  /**
   * @var \Uzivatel[]
   */
  private $storytellersCache;
  /**
   * @var array|int[]
   */
  private $keyUnifyDepth = ['storytellers' => ['fromName' => self::UNIFY_UP_TO_LETTERS, 'fromNick' => self::UNIFY_UP_TO_LETTERS]];
  /**
   * @var string
   */
  private $mutexKey;
  /**
   * @var Mutex
   */
  private $mutexPattern;
  /**
   * @var Mutex
   */
  private $mutexForProgramLine;
  /**
   * @var string
   */
  private $editActivityUrlSkeleton;
  /**
   * @var string
   */
  private $baseUrl;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    int $currentYear,
    \DateTimeInterface $now,
    string $editActivityUrlSkeleton,
    Logovac $logovac,
    Mutex $mutexPattern,
    string $baseUrl
  ) {
    $this->userId = $userId;
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->currentYear = $currentYear;
    $this->now = $now;
    $this->logovac = $logovac;
    $this->mutexPattern = $mutexPattern;
    $this->editActivityUrlSkeleton = $editActivityUrlSkeleton;
    $this->baseUrl = $baseUrl;
  }

  public function importujAktivity(string $spreadsheetId): ResultOfActivitiesImport {
    $result = new ResultOfActivitiesImport();
    try {
      $processedFileNameResult = $this->getProcessedFileName($spreadsheetId);
      if ($processedFileNameResult->isError()) {
        $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $processedFileNameResult->getError()));
        return $result;
      }
      $processedFileName = $processedFileNameResult->getSuccess();
      unset($processedFileNameResult);
      $result->setProcessedFilename($processedFileName);

      $activitiesValuesResult = $this->getIndexedValues($spreadsheetId);
      if ($activitiesValuesResult->isError()) {
        $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $activitiesValuesResult->getError()));
        return $result;
      }
      $activitiesValues = $activitiesValuesResult->getSuccess();
      unset($activitiesValuesResult);

      $singleProgramLineResult = $this->guardSingleProgramLineOnly($activitiesValues, $processedFileName);
      if ($singleProgramLineResult->isError()) {
        $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $singleProgramLineResult->getError()));
        return $result;
      }
      /** @var \Typ $singleProgramLine */
      $singleProgramLine = $singleProgramLineResult->getSuccess();
      unset($singleProgramLineResult);

      if (!$this->getExclusiveLock($singleProgramLine->nazev())) {
        $result->addWarningMessage(sprintf(
          "Právě probíhá jiný import aktivit z programové linie '%s'. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.",
          mb_ucfirst($singleProgramLine->nazev())
        ));
        return $result;
      }

      $potentialImageUrlsPerActivity = [];
      foreach ($activitiesValues as $activityValues) {
        $originalActivityIdResult = $this->getActivityId($activityValues);
        if ($originalActivityIdResult->isError()) {
          $result->addErrorMessage($originalActivityIdResult->getError());
          continue;
        }
        $oiginalActivityId = $originalActivityIdResult->getSuccess();

        $originalActivity = null;
        if ($oiginalActivityId) {
          $originalActivityResult = $this->getValidatedOriginalActivity($oiginalActivityId);
          if ($originalActivityResult->isError()) {
            $errorMessage = $this->getErrorMessageWithSkippedActivityNote($originalActivityResult);
            $result->addErrorMessage($errorMessage);
            continue;
          }
          $originalActivity = $originalActivityResult->getSuccess();
        }

        $validatedValuesResult = $this->validateValues($singleProgramLine, $activityValues, $originalActivity);
        if ($validatedValuesResult->isError()) {
          $errorMessage = $this->getErrorMessageWithSkippedActivityNote($validatedValuesResult);
          $result->addErrorMessage($errorMessage);
          continue;
        }
        $validatedValues = $validatedValuesResult->getSuccess();
        unset($validatedValuesResult);
        [
          'values' => $values,
          'longAnnotation' => $longAnnotation,
          'storytellersIds' => $storytellersIds,
          'tagIds' => $tagIds,
          'potentialImageUrls' => $potentialImageUrls,
        ] = $validatedValues;

        $importActivityResult = $this->importActivity(
          $values,
          $longAnnotation,
          $storytellersIds,
          $tagIds,
          $singleProgramLine,
          $originalActivity
        );
        if ($importActivityResult->hasWarnings()) {
          foreach ($importActivityResult->getWarnings() as $warning) {
            $result->addWarningMessage($warning);
          }
        }
        if ($importActivityResult->isError()) {
          $errorMessage = $this->getErrorMessageWithSkippedActivityNote($importActivityResult);
          $result->addErrorMessage($errorMessage);
          continue;
        }
        ['message' => $successMessage, 'importedActivityId' => $importedActivityId] = $importActivityResult->getSuccess();
        $result->addSuccessMessage($successMessage);
        unset($importActivityResult);

        if (count($potentialImageUrls) > 0) {
          $potentialImageUrlsPerActivity[$importedActivityId] = $potentialImageUrls;
        }

        $result->incrementImportedCount();
      }
    } catch (\Google_Service_Exception $exception) {
      $result->addErrorMessage('Google sheets API je dočasně nedostupné. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.');
      $this->logovac->zaloguj($exception);
      $this->releaseExclusiveLock();
      return $result;
    }
    $savingImagesResult = $this->saveImages($potentialImageUrlsPerActivity);
    if ($savingImagesResult->hasWarnings()) {
      $result->addWarningMessages($savingImagesResult->getWarnings());
    }
    $this->releaseExclusiveLock();
    return $result;
  }

  private function saveImages(array $potentialImageUrlsPerActivity): ResultOfImportStep {
    if (count($potentialImageUrlsPerActivity) === 0) {
      return ResultOfImportStep::success(null);
    }
    $warnings = [];
    $imageUrls = [];
    /** @var \Aktivita[] $imageUrlsToActivity */
    $imageUrlsToActivity = [];
    /** @var \Aktivita[] $activities */
    $activities = [];
    $activityIds = array_keys($potentialImageUrlsPerActivity);
    foreach (\Aktivita::zIds($activityIds) as $activity) {
      $activities[$activity->id()] = $activity;
      unset($activity);
    }
    foreach ($potentialImageUrlsPerActivity as $activityId => $potentialImageUrls) {
      $activity = $activities[$activityId];
      foreach ($potentialImageUrls as $potentialImageUrl) {
        // Image URL is same as current, therefore came from an export and there is no change from it
        if ($potentialImageUrl === $activity->urlObrazku($this->baseUrl)) {
          continue;
        }
        $imageUrls[] = $potentialImageUrl;
        $imageUrlsToActivity[$potentialImageUrl] = $activity;
        unset($activity);
      }
    }
    $imageUrls = array_unique($imageUrls);
    ['files' => $downloadedImages, 'errors' => $downloadingImagesErrors] = hromadneStazeni($imageUrls, 10);
    if (count($downloadingImagesErrors) > 0) {
      $warnings[] = sprintf('Některé obrázky se nepodařilo stáhnout: %s', implode(', ', $downloadingImagesErrors));
    }
    foreach ($downloadedImages as $imageUrl => $downloadedImage) {
      $activity = $imageUrlsToActivity[$imageUrl];
      try {
        $obrazek = \Obrazek::zSouboru($downloadedImage);
        $activity->obrazek($obrazek);
      } catch (\ObrazekException $obrazekException) {
        $warnings[] = sprintf(
          'Nepodařilo se uložit obrázek %s k aktivitě %s z důvodu: %s',
          $imageUrl,
          $this->describeActivity($activity), $obrazekException->getMessage()
        );
        continue;
      }
    }
    return ResultOfImportStep::successWithWarnings(true, $warnings);
  }

  private function getProcessedFileName(string $spreadsheetId): ResultOfImportStep {
    try {
      $filename = $this->googleDriveService->getFileName($spreadsheetId);
    } catch (GoogleConnectionException | \Google_Service_Exception $connectionException) {
      $this->logovac->zaloguj($connectionException);
      return ResultOfImportStep::error('Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.');
    }
    if ($filename === null) {
      return ResultOfImportStep::error(sprintf("Žádný soubor nebyl na Google API nalezen pod ID '$spreadsheetId'."));
    }
    return ResultOfImportStep::success($filename);
  }

  private function getExclusiveLock(string $identifier): bool {
    $mutex = $this->createMutexForProgramLine($identifier);
    return $mutex->cekejAZamkni(3500 /* milliseconds */, new \DateTimeImmutable('+1 minute'), $this->createMutexKey($identifier), $this->userId);
  }

  private function createMutexForProgramLine(string $identifier): Mutex {
    if (!$this->mutexForProgramLine) {
      $this->mutexForProgramLine = $this->mutexPattern->dejProPodAkci($identifier);
    }
    return $this->mutexForProgramLine;
  }

  private function releaseExclusiveLock() {
    $this->getMutexForProgramLine()->odemkni($this->getMutexKey());
  }

  private function getMutexForProgramLine(): Mutex {
    if (!$this->mutexForProgramLine) {
      throw new ImportAktivitException('Mutex for imported program line does not exists yet');
    }
    return $this->mutexForProgramLine;
  }

  private function createMutexKey(string $programLine): string {
    if ($this->mutexKey === null) {
      $this->mutexKey = uniqid($programLine . '-', true);
    }
    return $this->mutexKey;
  }

  private function getMutexKey(): string {
    if (!$this->mutexKey) {
      throw new ImportAktivitException('Mutex key is empty');
    }
    return $this->mutexKey;
  }

  private function findParentInstanceId(?\Aktivita $originalActivity): ?int {
    if ($originalActivity) {
      $instanceId = $originalActivity->patriPod();
      return $instanceId
        ? (int)$instanceId
        : null;
    }
    return null;
  }

  private function findNewInstanceParentActivityId(?string $url, int $programLineId): ?int {
    if (!$url) {
      return null;
    }
    $parentActivityId = dbOneCol(<<<SQL
SELECT MIN(akce_seznam.id_akce)
FROM akce_seznam
WHERE akce_seznam.url_akce = $1 AND akce_seznam.rok = $2 AND akce_seznam.typ = $3
SQL
      , [$url, $this->currentYear, $programLineId]
    );
    return $parentActivityId
      ? (int)$parentActivityId
      : null;
  }

  private function getValidatedOriginalActivity(int $id): ResultOfImportStep {
    $aktivita = $this->findOldActivityById($id);
    if ($aktivita) {
      return ResultOfImportStep::success($aktivita);
    }
    return ResultOfImportStep::error(sprintf('Aktivita s ID %d neexistuje. Nelze ji proto importem upravit.', $id));
  }

  private function findOldActivityById(int $id): ?\Aktivita {
    if (!array_key_exists($id, $this->oldActivities)) {
      $this->oldActivities[$id] = \Aktivita::zId($id);
    }
    return $this->oldActivities[$id];
  }

  private function getActivityId(array $activityValues): ResultOfImportStep {
    if ($activityValues[ExportAktivitSloupce::ID_AKTIVITY]) {
      return ResultOfImportStep::success((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
    }
    return ResultOfImportStep::success(null);
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

  private function getIndexedValues(string $spreadsheetId): ResultOfImportStep {
    try {
      $rawValues = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
    } catch (GoogleApiException $exception) {
      $this->logovac->zaloguj($exception);
      return ResultOfImportStep::error('Google Sheets API je dočasně nedostupné, zkuste to znovu za chvíli.');
    }
    $cleansedValuesResult = $this->cleanseValues($rawValues);
    if ($cleansedValuesResult->isError()) {
      return ResultOfImportStep::error($cleansedValuesResult->getError());
    }
    $cleansedValues = $cleansedValuesResult->getSuccess();
    $cleansedHeaderResult = $this->getCleansedHeader($cleansedValues);
    if ($cleansedHeaderResult->isError()) {
      return ResultOfImportStep::error($cleansedHeaderResult->getError());
    }
    $cleansedHeader = $cleansedHeaderResult->getSuccess();
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
        return ResultOfImportStep::error(
          sprintf('Některým sloupcům chybí název a to na pozicích %s.', implode(',', $positionsOfValuesWithoutHeaders))
        );
      }
      $indexedValues[] = $indexedRow;
    }
    return ResultOfImportStep::success($indexedValues);
  }

  private function getCleansedHeader(array $values): ResultOfImportStep {
    $unifiedKnownColumns = [];
    foreach (ExportAktivitSloupce::vsechnySloupce() as $knownColumn) {
      $keyFromColumn = self::toUnifiedKey($knownColumn, $unifiedKnownColumns, self::UNIFY_UP_TO_LETTERS);
      $unifiedKnownColumns[$keyFromColumn] = $knownColumn;
    }
    $header = reset($values);
    $cleansedHeader = [];
    $unknownColumns = [];
    $emptyColumnsPositions = [];
    foreach ($header as $index => $value) {
      $unifiedValue = self::toUnifiedKey($value, [], self::UNIFY_UP_TO_LETTERS);
      if (array_key_exists($unifiedValue, $unifiedKnownColumns)) {
        $cleansedHeader[$index] = $unifiedKnownColumns[$unifiedValue];
      } else if ($value === '') {
        $emptyColumnsPositions[$index] = $index + 1;
      } else {
        $unknownColumns[] = $value;
      }
    }
    if (count($unknownColumns) > 0) {
      return ResultOfImportStep::error(
        sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
          return "'$value'";
        }, $unknownColumns)))
      );
    }
    if (count($cleansedHeader) === 0) {
      return ResultOfImportStep::error('Chybí názvy sloupců v prvním řádku');
    }
    if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
      return ResultOfImportStep::error(sprintf('Některé náxvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)));
    }
    return ResultOfImportStep::success($cleansedHeader);
  }

  private function cleanseValues(array $values): ResultOfImportStep {
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
      return ResultOfImportStep::error('Žádná data. Import je prázdný.');
    }
    return ResultOfImportStep::success($cleansedValues);
  }

  private function importActivity(
    $values,
    $longAnnotation,
    $storytellersIds,
    $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ResultOfImportStep {
    if ($originalActivity) {
      if (!$originalActivity->bezpecneEditovatelna()) {
        return ResultOfImportStep::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'",
          $this->describeActivity($originalActivity), $originalActivity->stav()->nazev()
        ));
      }
      if ($originalActivity->zacatek() && $originalActivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
        return ResultOfImportStep::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s)",
          $this->describeActivity($originalActivity), $originalActivity->zacatek()->formatCasNaMinutyStandard()
        ));
      }
      if ($originalActivity->konec() && $originalActivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
        return ResultOfImportStep::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s)",
          $this->describeActivity($originalActivity),
          $originalActivity->konec()->formatCasNaMinutyStandard()
        ));
      }
    }

    $storytellersAccessibilityResult = $this->checkStorytellersAccessibility(
      $storytellersIds,
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $originalActivity,
      $values
    );
    if ($storytellersAccessibilityResult->isError()) {
      return ResultOfImportStep::error($storytellersAccessibilityResult->getError());
    }
    $storytellersAccessibilityWarnings = $storytellersAccessibilityResult->getWarnings();
    $availableStorytellerIds = $storytellersAccessibilityResult->getSuccess();

    $locationAccessibilityResult = $this->checkLocationByAccessibility(
      $values[AktivitaSqlSloupce::LOKACE],
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $originalActivity
        ? $originalActivity->id()
        : null,
      $values
    );
    if ($locationAccessibilityResult->isError()) {
      return ResultOfImportStep::error($locationAccessibilityResult->getError());
    }
    $locationAccessibilityWarnings = $locationAccessibilityResult->getWarnings();

    /** @var  \Aktivita $importedActivity */
    $savedActivityResult = $this->saveActivity(
      $values,
      $longAnnotation,
      $availableStorytellerIds,
      $tagIds,
      $singleProgramLine,
      $originalActivity
    );
    $importedActivity = $savedActivityResult->getSuccess();

    if ($savedActivityResult->isError()) {
      return ResultOfImportStep::error($savedActivityResult->getError());
    }
    $warnings = array_filter(array_merge($storytellersAccessibilityWarnings, $locationAccessibilityWarnings));
    if ($originalActivity) {
      return ResultOfImportStep::successWithWarnings(
        [
          'message' => sprintf('Upravena existující aktivita %s', $this->describeActivity($importedActivity)),
          'importedActivityId' => $importedActivity->id(),
        ],
        $warnings
      );
    }
    if ($importedActivity->patriPod()) {
      return ResultOfImportStep::successWithWarnings(
        [
          'message' => sprintf(
            'Nahrána nová aktivita %s jako %d. <strong>instance</strong> k hlavní aktivitě %s.',
            $this->describeActivity($importedActivity),
            $importedActivity->pocetInstanci(),
            $this->describeActivity($importedActivity->patriPodAktivitu())
          ),
          'importedActivityId' => $importedActivity->id(),
        ],
        $warnings
      );
    }
    return ResultOfImportStep::successWithWarnings(
      [
        'message' => sprintf('Nahrána nová aktivita %s', $this->describeActivity($importedActivity)),
        'importedActivityId' => $importedActivity->id(),
      ],
      $warnings
    );
  }

  private function guardSingleProgramLineOnly(array $activitiesValues, string $processedFileName): ResultOfImportStep {
    $programLines = [];
    foreach ($activitiesValues as $row) {
      $programLine = null;
      $programLineId = null;
      $programLineValue = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLineValue) {
        $programLine = $this->getProgramLineFromValue((string)$programLineValue);
      }
      if (!$programLine && $row[ExportAktivitSloupce::ID_AKTIVITY]) {
        $aktivita = \Aktivita::zId($row[ExportAktivitSloupce::ID_AKTIVITY]);
        if ($aktivita && $aktivita->typ()) {
          $programLine = $aktivita->typ();
        }
      }
      if ($programLine && !array_key_exists($programLine->id(), $programLines)) {
        $programLines[$programLineId] = $programLine;
      }
    }
    if (count($programLines) > 1) {
      return ResultOfImportStep::error(sprintf(
        'Importovat lze pouze jednu programovou linii. Importní soubor %s jich má %d: %s.',
        $processedFileName,
        count($programLines),
        implode(
          ',',
          self::wrapByQuotes(array_map(static function (\Typ $typ) {
            return $typ->nazev();
          }, $programLines))
        )));
    }
    if (count($programLines) === 0) {
      return ResultOfImportStep::error('V importovaném souboru chybí programová linie, nebo alespoň existující aktivita s nastavenou programovou linií.');
    }
    return ResultOfImportStep::success(reset($programLines));
  }

  private function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?\Aktivita $originalActivity, array $values): ResultOfImportStep {
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return ResultOfImportStep::success($storytellersIds);
    }
    /** @var DateTimeCz $zacatek */
    /** @var DateTimeCz $konec */
    ['start' => $zacatek, 'end' => $konec] = $rangeDates;
    $occupiedStorytellers = dbArrayCol(<<<SQL
SELECT akce_organizatori.id_uzivatele, GROUP_CONCAT(akce_organizatori.id_akce SEPARATOR ',') AS activity_ids
FROM akce_organizatori
JOIN akce_seznam ON akce_organizatori.id_akce = akce_seznam.id_akce
WHERE akce_seznam.zacatek >= $1
AND akce_seznam.konec <= $2
AND CASE
    WHEN $3 IS NULL THEN TRUE
    ELSE akce_seznam.id_akce != $3
    END
GROUP BY akce_organizatori.id_uzivatele
SQL
      , [$zacatek->format(DateTimeCz::FORMAT_DB), $konec->format(DateTimeCz::FORMAT_DB), $originalActivity ? $originalActivity->id() : null]
    );
    $conflictingStorytellers = array_intersect_key($occupiedStorytellers, array_fill_keys($storytellersIds, true));
    if (!$conflictingStorytellers) {
      return ResultOfImportStep::success($storytellersIds);
    }
    $warnings = [];
    foreach ($conflictingStorytellers as $conflictingStorytellerId => $implodedActivityIds) {
      $activityIds = explode(',', $implodedActivityIds);
      $warnings[] = sprintf(
        'Vypravěč %s je v čase od %s do %s na %s %s. K aktivitě %s nebyl přiřazen.',
        $this->describeUserById((int)$conflictingStorytellerId),
        $zacatek->formatCasStandard(),
        $konec->formatCasStandard(),
        count($activityIds) === 1
          ? 'aktivitě'
          : 'aktivitách',
        implode(' a ', array_map(function ($activityId) {
          return $this->describeActivityById((int)$activityId);
        }, $activityIds)),
        $this->describeActivityBySqlMappedValues($values, $originalActivity)
      );
    }
    return ResultOfImportStep::successWithWarnings(array_diff($storytellersIds, array_keys($occupiedStorytellers)), $warnings);
  }

  private function createRangeDates(?string $zacatekString, ?string $konecString): ?array {
    if ($zacatekString === null && $konecString === null) {
      // nothing to check, we do not know the activity time
      return null;
    }
    $zacatek = $zacatekString
      ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $zacatekString)
      : null;
    $konec = $konecString
      ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $konecString)
      : null;
    if (!$zacatek) {
      $zacatek = (clone $konec)->modify('-1 hour');
    }
    if (!$konec) {
      $konec = (clone $zacatek)->modify('+1 hour');
    }
    return ['start' => $zacatek, 'end' => $konec];
  }

  private function checkLocationByAccessibility(
    ?int $locationId,
    ?string $zacatekString,
    ?string $konecString,
    ?int $currentActivityId,
    array $values
  ): ResultOfImportStep {
    if ($locationId === null) {
      return ResultOfImportStep::success(null);
    }
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return ResultOfImportStep::success(true);
    }
    /** @var DateTimeCz $zacatek */
    /** @var DateTimeCz $konec */
    ['start' => $zacatek, 'end' => $konec] = $rangeDates;
    $locationOccupyingActivityId = dbOneCol(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE akce_seznam.lokace = $1
AND akce_seznam.zacatek >= $2
AND akce_seznam.konec <= $3
AND CASE
    WHEN $4 IS NULL THEN TRUE
    ELSE akce_seznam.id_akce != $4
    END
LIMIT 1
SQL
      , [$locationId, $zacatek->format(DateTimeCz::FORMAT_DB), $konec->format(DateTimeCz::FORMAT_DB), $currentActivityId]
    );
    if (!$locationOccupyingActivityId) {
      return ResultOfImportStep::success($locationId);
    }
    $currentActivity = \Aktivita::zId($currentActivityId);
    return ResultOfImportStep::successWithWarnings(
      true,
      [
        sprintf(
          'Místnost %s je někdy mezi %s a %s již zabraná jinou aktivitou %s. Nahrávaná aktivita %s byla proto %s.',
          $this->describeLocationById($locationId),
          $zacatek->formatCasNaMinutyStandard(),
          $konec->formatCasNaMinutyStandard(),
          $this->describeActivityById((int)$locationOccupyingActivityId),
          $this->describeActivityBySqlMappedValues($values, $currentActivity),
          $currentActivity && $currentActivity->lokace()
            ? sprintf('ponechána v původní místnosti %s', \Aktivita::zId($currentActivityId)->lokace()->nazev())
            : 'nahrána <strong>bez</strong> místnosti'
        ),
      ]
    );
  }

  private function describeLocationById(int $locationId): string {
    $lokace = \Lokace::zId($locationId);
    return sprintf('%s (%s)', $lokace->nazev(), $lokace->id());
  }

  private function describeUserById(int $userId): string {
    $uzivatel = \Uzivatel::zId($userId);
    return sprintf('%s (%s)', $uzivatel->jmenoNick(), $uzivatel->id());
  }

  private function describeActivityById(int $activityId): string {
    $aktivita = \Aktivita::zId($activityId);
    return $this->describeActivity($aktivita);
  }

  private function describeActivity(\Aktivita $activity): string {
    return $this->getLinkToActivity($activity);
  }

  private function saveActivity(
    array $values,
    ?string $longAnnotation,
    array $storytellersIds,
    array $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ResultOfImportStep {
    try {
      if (!$values[AktivitaSqlSloupce::ID_AKCE] && !$values[AktivitaSqlSloupce::PATRI_POD]) {
        $newInstanceParentActivityId = $this->findNewInstanceParentActivityId($values[AktivitaSqlSloupce::URL_AKCE], $singleProgramLine->id());
        if ($newInstanceParentActivityId) {
          $newInstance = $this->createInstanceForParentActivity($newInstanceParentActivityId);
          $values[AktivitaSqlSloupce::ID_AKCE] = $newInstance->id();
          $values[AktivitaSqlSloupce::PATRI_POD] = $newInstance->patriPod();
        }
      }
      $savedActivity = \Aktivita::uloz($values, $longAnnotation, $storytellersIds, $tagIds);
      return ResultOfImportStep::success($savedActivity);
    } catch (\Exception $exception) {
      $this->logovac->zaloguj($exception);
      return ResultOfImportStep::error(sprintf(
        'Nepodařilo se uložit aktivitu %s: %s',
        $this->describeActivityByInputValues($values, $originalActivity), $exception->getMessage()
      ));
    }
  }

  private function createInstanceForParentActivity(int $parentActivityId): \Aktivita {
    $parentActivity = \Aktivita::zId($parentActivityId);
    return $parentActivity->instancuj();
  }

  private function validateValues(\Typ $singleProgramLine, array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $sanitizedValues = [];
    if ($originalActivity) {
      $sanitizedValues = $originalActivity->rawDb();
      // remove values originating in another tables
      $sanitizedValues = array_intersect_key(
        $sanitizedValues,
        array_fill_keys(AktivitaSqlSloupce::vsechnySloupce(), true)
      );
    }
    $tagIds = null;
    $storytellersIds = null;

    $sanitizedValues[AktivitaSqlSloupce::ID_AKCE] = $originalActivity
      ? $originalActivity->id()
      : null;

    $programLineIdResult = $this->getValidatedProgramLineId($activityValues, $originalActivity);
    if ($programLineIdResult->isError()) {
      return ResultOfImportStep::error($programLineIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TYP] = $programLineIdResult->getSuccess();
    unset($programLineIdResult);

    $activityUrlResult = $this->getValidatedUrl($activityValues, $singleProgramLine, $originalActivity);
    if ($activityUrlResult->isError()) {
      return ResultOfImportStep::error($activityUrlResult->getError());
    }
    $activityUrl = $activityUrlResult->getSuccess();
    $sanitizedValues[AktivitaSqlSloupce::URL_AKCE] = $activityUrl;
    unset($activityUrlResult);

    $activityNameResult = $this->getValidatedActivityName($activityValues, $activityUrl, $singleProgramLine, $originalActivity);
    if ($activityNameResult->isError()) {
      return ResultOfImportStep::error($activityNameResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::NAZEV_AKCE] = $activityNameResult->getSuccess();
    unset($activityNameResult);

    $shortAnnotationResult = $this->getValidatedShortAnnotation($activityValues, $originalActivity);
    if ($shortAnnotationResult->isError()) {
      return ResultOfImportStep::error($shortAnnotationResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS_KRATKY] = $shortAnnotationResult->getSuccess();
    unset($shortAnnotationResult);

    $tagIdsResult = $this->getValidatedTagIds($activityValues, $originalActivity);
    if ($tagIdsResult->isError()) {
      return ResultOfImportStep::error($tagIdsResult->getError());
    }
    $tagIds = $tagIdsResult->getSuccess();
    unset($tagIdsResult);

    $longAnnotationResult = $this->getValidatedLongAnnotation($activityValues, $originalActivity);
    if ($longAnnotationResult->isError()) {
      return ResultOfImportStep::error($longAnnotationResult->getError());
    }
    $longAnnotation = $longAnnotationResult->getSuccess();
    unset($longAnnotationResult);

    $activityStartResult = $this->getValidatedStart($activityValues, $originalActivity);
    if ($activityStartResult->isError()) {
      return ResultOfImportStep::error($activityStartResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ZACATEK] = $activityStartResult->getSuccess();
    unset($activityStartResult);

    $activityEndResult = $this->getValidatedEnd($activityValues, $originalActivity);
    if ($activityEndResult->isError()) {
      return ResultOfImportStep::error($activityEndResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KONEC] = $activityEndResult->getSuccess();
    unset($activityEndResult);

    $locationIdResult = $this->getValidatedLocationId($activityValues, $originalActivity);
    if ($locationIdResult->isError()) {
      return ResultOfImportStep::error($locationIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::LOKACE] = $locationIdResult->getSuccess();
    unset($locationIdResult);

    $storytellersIdsResult = $this->getValidatedStorytellersIds($activityValues, $originalActivity);
    if ($storytellersIdsResult->isError()) {
      return ResultOfImportStep::error($storytellersIdsResult->getError());
    }
    $storytellersIds = $storytellersIdsResult->getSuccess();
    unset($storytellersIdsResult);

    $unisexCapacityResult = $this->getValidatedUnisexCapacity($activityValues, $originalActivity);
    if ($unisexCapacityResult->isError()) {
      return ResultOfImportStep::error($unisexCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA] = $unisexCapacityResult->getSuccess();
    unset($unisexCapacityResult);

    $menCapacityResult = $this->getValidatedMenCapacity($activityValues, $originalActivity);
    if ($menCapacityResult->isError()) {
      return ResultOfImportStep::error($menCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_M] = $menCapacityResult->getSuccess();
    unset($menCapacityResult);

    $womenCapacityResult = $this->getValidatedWomenCapacity($activityValues, $originalActivity);
    if ($womenCapacityResult->isError()) {
      return ResultOfImportStep::error($womenCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_F] = $womenCapacityResult->getSuccess();
    unset($womenCapacityResult);

    $forTeamResult = $this->getValidatedForTeam($activityValues, $originalActivity);
    if ($forTeamResult->isError()) {
      return ResultOfImportStep::error($forTeamResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAMOVA] = $forTeamResult->getSuccess();
    unset($forTeamResult);

    $minimalTeamCapacityResult = $this->getValidatedMinimalTeamCapacity($activityValues, $originalActivity);
    if ($minimalTeamCapacityResult->isError()) {
      return ResultOfImportStep::error($minimalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MIN] = $minimalTeamCapacityResult->getSuccess();
    unset($minimalTeamCapacityResult);

    $maximalTeamCapacityResult = $this->getValidatedMaximalTeamCapacity($activityValues, $originalActivity);
    if ($maximalTeamCapacityResult->isError()) {
      return ResultOfImportStep::error($maximalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MAX] = $maximalTeamCapacityResult->getSuccess();
    unset($maximalTeamCapacityResult);

    $priceResult = $this->getValidatedPrice($activityValues, $originalActivity);
    if ($priceResult->isError()) {
      return ResultOfImportStep::error($priceResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::CENA] = $priceResult->getSuccess();
    unset($priceResult);

    $withoutDiscountResult = $this->getValidatedWithoutDiscount($activityValues, $originalActivity);
    if ($withoutDiscountResult->isError()) {
      return ResultOfImportStep::error($withoutDiscountResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::BEZ_SLEVY] = $withoutDiscountResult->getSuccess();
    unset($withoutDiscountResult);

    $equipmentResult = $this->getValidatedEquipment($activityValues, $originalActivity);
    if ($equipmentResult->isError()) {
      return ResultOfImportStep::error($equipmentResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::VYBAVENI] = $equipmentResult->getSuccess();
    unset($equipmentResult);

    $stateIdResult = $this->getValidatedStateId($activityValues, $originalActivity);
    if ($stateIdResult->isError()) {
      return ResultOfImportStep::error($stateIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::STAV] = $stateIdResult->getSuccess();
    unset($stateIdResult);

    $yearResult = $this->getValidatedYear($originalActivity);
    if ($yearResult->isError()) {
      return ResultOfImportStep::error($yearResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ROK] = $yearResult->getSuccess();
    unset($yearResult);

    $instanceIdResult = $this->getValidatedInstanceId($originalActivity);
    if ($instanceIdResult->isError()) {
      return ResultOfImportStep::error($instanceIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::PATRI_POD] = $instanceIdResult->getSuccess();
    unset($instanceIdResult);

    $potentialImageUrlsResult = $this->getPotencialImageUrls($activityValues, $activityUrl, $originalActivity);
    if ($potentialImageUrlsResult->isError()) {
      return ResultOfImportStep::error($potentialImageUrlsResult->getError());
    }
    $potentialImageUrls = $potentialImageUrlsResult->getSuccess();
    unset($potentialImageUrlsResult);

    return ResultOfImportStep::success([
      'values' => $sanitizedValues,
      'longAnnotation' => $longAnnotation,
      'storytellersIds' => $storytellersIds,
      'tagIds' => $tagIds,
      'potentialImageUrls' => $potentialImageUrls,
    ]);
  }

  private function getErrorMessageWithSkippedActivityNote(ResultOfImportStep $resultOfImportStep): string {
    if (!$resultOfImportStep->isError()) {
      throw new \LogicException('Result of import step should be an error, got ' . $this->getResultTypeName($resultOfImportStep));
    }
    return sprintf('%s Aktivita byla <strong>přeskočena</strong>.', $resultOfImportStep->getError());
  }

  private function getResultTypeName(ResultOfImportStep $resultOfImportStep): string {
    $nameParts = [];
    if ($resultOfImportStep->isError()) {
      $nameParts[] = 'error';
    }
    if ($resultOfImportStep->isSuccess()) {
      $nameParts[] = 'success';
    }
    if ($resultOfImportStep->hasWarnings()) {
      $nameParts[] = 'warnings';
    }
    return implode(',', $nameParts);
  }

  private function getPotencialImageUrls(array $activityValues, string $activityUrl, ?\Aktivita $originalActivity): ResultOfImportStep {
    $imageUrl = $activityValues[ExportAktivitSloupce::OBRAZEK] ?? null;
    if (!$imageUrl) {
      return ResultOfImportStep::success([]);
    }
    if (preg_match('~[.](jpg|png|gif)$~i', $imageUrl)) {
      return ResultOfImportStep::success([$imageUrl]);
    }
    $imageUrlWithoutExtension = rtrim($imageUrl, '/') . '/' . $activityUrl;
    return ResultOfImportStep::success([
      $imageUrlWithoutExtension . '.jpg',
      $imageUrlWithoutExtension . '.png',
      $imageUrlWithoutExtension . '.gif',
    ]);
  }

  private function getValidatedStateId(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $stateValue = $activityValues[ExportAktivitSloupce::STAV] ?? null;
    if ((string)$stateValue === '') {
      return ResultOfImportStep::success($originalActivity && $originalActivity->stav()
        ? $originalActivity->stav()->id()
        : \Stav::NOVA
      );
    }
    $state = $this->getStateFromValue((string)$stateValue);
    if ($state) {
      return ResultOfImportStep::success($state->id());
    }
    return ResultOfImportStep::error(sprintf(
      "Neznámý stav '%s' u aktivity %s",
      $stateValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getStateFromValue(string $StateValue): ?\Stav {
    $StateInt = (int)$StateValue;
    if ($StateInt > 0) {
      return $this->getStateById($StateInt);
    }
    return $this->getStateByName($StateValue);
  }

  private function getStateById(int $id): ?\Stav {
    return $this->getStatesCache()['id'][$id] ?? null;
  }

  private function getStateByName(string $name): ?\Stav {
    return $this->getStatesCache()['keyFromName'][self::toUnifiedKey(mb_substr($name, 0, 3, 'UTF-8'), [])] ?? null;
  }

  private function getStatesCache(): array {
    if (!$this->StatesCache) {
      $this->StatesCache = ['id' => [], 'keyFromName' => []];
      $States = \Stav::zVsech();
      foreach ($States as $State) {
        $this->StatesCache['id'][$State->id()] = $State;
        $keyFromName = self::toUnifiedKey(mb_substr($State->nazev(), 0, 3, 'UTF-8'), array_keys($this->StatesCache['keyFromName']));
        $this->StatesCache['keyFromName'][$keyFromName] = $State;
      }
    }
    return $this->StatesCache;
  }

  private function getValidatedEquipment(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $equipmentValue = $activityValues[ExportAktivitSloupce::VYBAVENI] ?? null;
    if ((string)$equipmentValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->vybaveni()
        : ''
      );
    }
    return ResultOfImportStep::success($equipmentValue);
  }

  private function getValidatedMinimalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$minimalTeamCapacityValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->tymMinKapacita()
        : 0
      );
    }
    $minimalTeamCapacity = (int)$minimalTeamCapacityValue;
    if ($minimalTeamCapacity > 0) {
      return ResultOfImportStep::success($minimalTeamCapacity);
    }
    if ((string)$minimalTeamCapacityValue === '0') {
      return ResultOfImportStep::success(0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná minimální kapacita týmu '%s' u aktivity %s. Očekáváme celé kladné číslo.",
      $minimalTeamCapacityValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedMaximalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$maximalTeamCapacityValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->tymMaxKapacita()
        : 0
      );
    }
    $maximalTeamCapacity = (int)$maximalTeamCapacityValue;
    if ($maximalTeamCapacity > 0) {
      return ResultOfImportStep::success($maximalTeamCapacity);
    }
    if ((string)$maximalTeamCapacityValue === '0') {
      return ResultOfImportStep::success(0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná maximální kapacita týmu '%s' u aktivity %s. Očekáváme celé kladné číslo.",
      $maximalTeamCapacityValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedForTeam(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $forTeamValue = $activityValues[ExportAktivitSloupce::JE_TYMOVA] ?? null;
    if ((string)$forTeamValue === '') {
      return ResultOfImportStep::success(
        $originalActivity && $originalActivity->tymova()
          ? 1
          : 0
      );
    }
    $forTeam = $this->parseBoolean($forTeamValue);
    if ($forTeam !== null) {
      return ResultOfImportStep::success(
        $forTeam
          ? 1
          : 0
      );
    }
    return ResultOfImportStep::error(sprintf(
      "Podivný zápis, zda je aktivita týmová '%s' u aktivity %s. Očekáváme pouze 1, 0, ano, ne.",
      $forTeamValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedStorytellersIds(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? null;
    if (!$storytellersString) {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->getOrganizatoriIds()
        : []
      );
    }
    $storytellersIds = [];
    $invalidStorytellersValues = [];
    $storytellersValues = array_map('trim', explode(',', $storytellersString));
    foreach ($storytellersValues as $storytellerValue) {
      $storyteller = $this->getStorytellerFromValue($storytellerValue);
      if (!$storyteller) {
        $invalidStorytellersValues[] = $storytellerValue;
      } else {
        $storytellersIds[] = $storyteller->id();
      }
    }
    if ($invalidStorytellersValues) {
      return ResultOfImportStep::error(sprintf(
        'Neznámí vypravěči %s pro aktivitu %s.',
        implode(',', array_map(static function (string $invalidStorytellerValue) {
          return "'$invalidStorytellerValue'";
        }, $invalidStorytellersValues)),
        $this->describeActivityByInputValues($activityValues, $originalActivity)
      ));
    }
    return ResultOfImportStep::success($storytellersIds);
  }

  private function getStorytellerFromValue(string $storytellerValue): ?\Uzivatel {
    $storytellerInt = (int)$storytellerValue;
    if ($storytellerInt > 0) {
      return $this->getStorytellerById($storytellerInt);
    }
    return $this->getStorytellerByEmail($storytellerValue)
      ?? $this->getStorytellerByName($storytellerValue)
      ?? $this->getStorytellerByNick($storytellerValue);
  }

  private function getStorytellerById(int $id): ?\Uzivatel {
    return $this->getStorytellersCache()['id'][$id] ?? null;
  }

  private function getStorytellerByEmail(string $email): ?\Uzivatel {
    if (strpos($email, '@') === false) {
      return null;
    }
    $key = self::toUnifiedKey($email, [], self::UNIFY_UP_TO_SPACES);
    return $this->getStorytellersCache()['keyFromEmail'][$key] ?? null;
  }

  private function getStorytellerByName(string $name): ?\Uzivatel {
    $key = self::toUnifiedKey($name, [], $this->keyUnifyDepth['storytellers']['fromName']);
    return $this->getStorytellersCache()['keyFromName'][$key] ?? null;
  }

  private function getStorytellerByNick(string $nick): ?\Uzivatel {
    $key = self::toUnifiedKey($nick, [], $this->keyUnifyDepth['storytellers']['fromNick']);
    return $this->getStorytellersCache()['keyFromNick'][$key] ?? null;
  }

  private function getStorytellersCache(): array {
    if (!$this->storytellersCache) {
      $this->storytellersCache = ['id' => [], 'keyFromEmail' => [], 'keyFromName' => [], 'keyFromNick' => [], 'storytellers' => []];

      $storytellers = \Uzivatel::organizatori();

      foreach ($storytellers as $storyteller) {
        $this->storytellersCache['id'][$storyteller->id()] = $storyteller;
        $keyFromEmail = self::toUnifiedKey($storyteller->mail(), array_keys($this->storytellersCache['keyFromEmail']), self::UNIFY_UP_TO_SPACES);
        $this->storytellersCache['keyFromEmail'][$keyFromEmail] = $storyteller;
      }

      for ($nameKeyUnifyDepth = $this->keyUnifyDepth['storytellers']['fromName']; $nameKeyUnifyDepth >= 0; $nameKeyUnifyDepth--) {
        $keyFromNameCache = [];
        foreach ($storytellers as $storyteller) {
          $name = $storyteller->jmeno();
          if ($name === '') {
            continue;
          }
          try {
            $keyFromCivilName = self::toUnifiedKey($name, array_keys($this->storytellersCache['keyFromName']), $nameKeyUnifyDepth);
            $keyFromNameCache[$keyFromCivilName] = $storyteller;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storytellersCache['keyFromName'] = $keyFromNameCache;
        $this->keyUnifyDepth['storytellers']['fromName'] = min($this->keyUnifyDepth['storytellers']['fromName'], $nameKeyUnifyDepth);
        break; // all names converted to unified and unique keys
      }

      for ($nickKeyUnifyDepth = $this->keyUnifyDepth['storytellers']['fromNick']; $nickKeyUnifyDepth >= 0; $nickKeyUnifyDepth--) {
        $keyFromNickCache = [];
        foreach ($storytellers as $storyteller) {
          $nick = $storyteller->nick();
          if ($nick === '') {
            continue;
          }
          try {
            $keyFromNick = self::toUnifiedKey($nick, array_keys($this->storytellersCache['keyFromNick']), $nickKeyUnifyDepth);
            $keyFromNickCache[$keyFromNick] = $storyteller;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storytellersCache['keyFromNick'] = $keyFromNickCache;
        $this->keyUnifyDepth['storytellers']['fromNick'] = min($this->keyUnifyDepth['storytellers']['fromNick'], $nickKeyUnifyDepth);
        break; // all nicks converted to unified and unique keys
      }
    }
    return $this->storytellersCache;
  }

  private function getValidatedLongAnnotation(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    if (!empty($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE])) {
      return ResultOfImportStep::success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE]);
    }
    return ResultOfImportStep::success($originalActivity
      ? $originalActivity->popis()
      : ''
    );
  }

  private function getValidatedTagIds(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
    if ($tagsString === '' && $originalActivity) {
      $tagIds = [];
      $invalidTagsValues = [];
      foreach ($originalActivity->tagy() as $tagValue) {
        $tag = $this->getTagFromValue($tagValue);
        if (!$tag) {
          $invalidTagsValues[] = $tagValue;
        } else {
          $tagIds[] = $tag->id();
        }
      }
      if ($invalidTagsValues) {
        trigger_error(
          E_USER_WARNING,
          sprintf('There are some strange tags coming from activity %s, which are unknown %s', $originalActivity->id(), implode(',', $invalidTagsValues))
        );
      }
      return ResultOfImportStep::success($tagIds);
    }
    $tagIds = [];
    $invalidTagsValues = [];
    $tagsValues = array_map('trim', explode(',', $tagsString));
    foreach ($tagsValues as $tagValue) {
      if ($tagValue === '') {
        continue;
      }
      $tag = $this->getTagFromValue($tagValue);
      if (!$tag) {
        $invalidTagsValues[] = $tagValue;
      } else {
        $tagIds[] = $tag->id();
      }
    }
    if ($invalidTagsValues) {
      return ResultOfImportStep::error(
        sprintf(
          'U aktivity %s jsou neznámé tagy %s.',
          $this->describeActivityByInputValues($activityValues, $originalActivity),
          implode(',', array_map(static function (string $invalidTagValue) {
              return "'$invalidTagValue'";
            },
              $invalidTagsValues
            )
          )
        )
      );
    }
    return ResultOfImportStep::success($tagIds);
  }

  private function getTagFromValue(string $tagValue): ?\Tag {
    $tagInt = (int)$tagValue;
    if ($tagInt > 0) {
      return $this->getTagById($tagInt);
    }
    return $this->getTagByName($tagValue);
  }

  private function getTagById(int $id): ?\Tag {
    return $this->getTagsCache()['id'][$id] ?? null;
  }

  private function getTagByName(string $name): ?\Tag {
    return $this->getTagsCache()['keyFromName'][self::toUnifiedKey($name, [])] ?? null;
  }

  private function getTagsCache(): array {
    if (!$this->tagsCache) {
      $this->tagsCache = ['id' => [], 'keyFromName' => []];
      $tags = \Tag::zVsech();
      foreach ($tags as $tag) {
        $this->tagsCache['id'][$tag->id()] = $tag;
        $keyFromName = self::toUnifiedKey($tag->nazev(), array_keys($this->tagsCache['keyFromName']));
        $this->tagsCache['keyFromName'][$keyFromName] = $tag;
      }
    }
    return $this->tagsCache;
  }

  private function getValidatedShortAnnotation(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    if (!empty($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE])) {
      return ResultOfImportStep::success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]);
    }
    return ResultOfImportStep::success($originalActivity
      ? $originalActivity->kratkyPopis()
      : ''
    );
  }

  private function getValidatedInstanceId(?\Aktivita $originalActivity): ResultOfImportStep {
    return ResultOfImportStep::success($this->findParentInstanceId($originalActivity));
  }

  private function getValidatedYear(?\Aktivita $originalActivity): ResultOfImportStep {
    if (!$originalActivity) {
      return ResultOfImportStep::success($this->currentYear);
    }
    $year = $originalActivity->zacatek()
      ? (int)$originalActivity->zacatek()->format('Y')
      : null;
    if (!$year) {
      $year = $originalActivity->konec()
        ? (int)$originalActivity->konec()->format('Y')
        : null;
    }
    if ($year) {
      if ($year !== $this->currentYear) {
        return ResultOfImportStep::error(sprintf(
          'Aktivita %s je pro ročník %d, ale teď je ročník %d.',
          $this->describeActivity($originalActivity),
          $year,
          $this->currentYear
        ));
      }
      return ResultOfImportStep::success($year);
    }
    return ResultOfImportStep::success($this->currentYear);
  }

  private function getValidatedWithoutDiscount(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $withoutDiscountValue = $activityValues[ExportAktivitSloupce::BEZ_SLEV] ?? null;
    if ((string)$withoutDiscountValue === '') {
      return ResultOfImportStep::success($originalActivity && $originalActivity->bezSlevy()
        ? 1
        : 0
      );
    }
    $withoutDiscount = $this->parseBoolean($withoutDiscountValue);
    if ($withoutDiscount !== null) {
      return ResultOfImportStep::success(
        $withoutDiscount
          ? 1
          : 0
      );
    }
    return ResultOfImportStep::error(sprintf(
      "Podivný zápis 'bez slevy': '%s' u aktivity %s. Očekáváme pouze 1, 0, ano, ne.",
      $withoutDiscountValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function parseBoolean($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    switch (substr((string)$value, 0, 1)) {
      case '0' :
      case 'n' :
      case 'f' :
        return false;
      case '1' :
      case 'a' :
      case 'y' :
        return true;
      default :
        return null;
    }
  }

  private function getValidatedUnisexCapacity(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_UNISEX] ?? null;
    if ((string)$capacityValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->getKapacitaUnisex()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return ResultOfImportStep::success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return ResultOfImportStep::success(0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná unisex kapacita '%s' u aktivity %s. Očekáváme celé kladné číslo.",
      $capacityValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedMenCapacity(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_MUZI] ?? null;
    if ((string)$capacityValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->getKapacitaMuzu()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return ResultOfImportStep::success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return ResultOfImportStep::success(0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná kapacita mužů '%s' u aktivity %s. Očekáváme celé kladné číslo.",
      $capacityValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedWomenCapacity(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_ZENY] ?? null;
    if ((string)$capacityValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->getKapacitaZen()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return ResultOfImportStep::success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return ResultOfImportStep::success(0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná kapacita žen '%s' u aktivity %s. Očekáváme celé kladné číslo.",
      $capacityValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedPrice(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
    if ((string)$priceValue === '') {
      return ResultOfImportStep::success($originalActivity
        ? $originalActivity->cenaZaklad()
        : 0.0
      );
    }
    $priceFloat = (float)$priceValue;
    if ($priceFloat !== 0.0) {
      return ResultOfImportStep::success($priceFloat);
    }
    if ((string)$priceFloat === '0' || (string)$priceFloat === '0.0') {
      return ResultOfImportStep::success(0.0);
    }
    return ResultOfImportStep::error(sprintf(
      "Podivná cena aktivity '%s' u aktivity %s. Očekáváme číslo.",
      $priceValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getValidatedLocationId(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $locationValue = $activityValues[ExportAktivitSloupce::MISTNOST] ?? null;
    if (!$locationValue) {
      if ($originalActivity) {
        return ResultOfImportStep::success($originalActivity->lokaceId());
      }
      return ResultOfImportStep::success(null);
    }
    $location = $this->getLocationFromValue((string)$locationValue);
    if ($location) {
      return ResultOfImportStep::success($location->id());
    }
    return ResultOfImportStep::error(sprintf(
      "Neznámá lokace '%s' u aktivity %s.",
      $locationValue,
      $this->describeActivityByInputValues($activityValues, $originalActivity)
    ));
  }

  private function getLocationFromValue(string $locationValue): ?\Lokace {
    $locationInt = (int)$locationValue;
    if ($locationInt > 0) {
      return $this->getProgramLocationById($locationInt);
    }
    return $this->getProgramLocationByName($locationValue);
  }

  private function getProgramLocationById(int $id): ?\Lokace {
    return $this->getProgramLocationsCache()['id'][$id] ?? null;
  }

  private function getProgramLocationByName(string $name): ?\Lokace {
    return $this->getProgramLocationsCache()['keyFromName'][self::toUnifiedKey($name, [], self::UNIFY_UP_TO_NUMBERS_AND_LETTERS)] ?? null;
  }

  private function getProgramLocationsCache(): array {
    if (!$this->programLocationsCache) {
      $this->programLocationsCache = ['id' => [], 'keyFromName' => []];
      $locations = \Lokace::zVsech();
      foreach ($locations as $location) {
        $this->programLocationsCache['id'][$location->id()] = $location;
        $keyFromName = self::toUnifiedKey($location->nazev(), array_keys($this->programLocationsCache['keyFromName']), self::UNIFY_UP_TO_NUMBERS_AND_LETTERS);
        $this->programLocationsCache['keyFromName'][$keyFromName] = $location;
      }
    }
    return $this->programLocationsCache;
  }

  private function getValidatedStart(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $startValue = $activityValues[ExportAktivitSloupce::ZACATEK] ?? null;
    if (!$startValue) {
      if (!$originalActivity) {
        return ResultOfImportStep::success(null);
      }
      $activityStart = $originalActivity->zacatek();
      return ResultOfImportStep::success($activityStart
        ? $activityStart->formatDb()
        : null
      );
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return ResultOfImportStep::error(
        sprintf(
          'U aktivity %s je sice začátek (%s), ale chybí u ní den.',
          $this->describeActivityByInputValues($activityValues, $originalActivity),
          $activityValues[ExportAktivitSloupce::ZACATEK]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK]);
  }

  private function getValidatedEnd(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $activityEndValue = $activityValues[ExportAktivitSloupce::KONEC] ?? null;
    if (!$activityEndValue) {
      if (!$originalActivity) {
        return ResultOfImportStep::success(null);
      }
      $activityEnd = $originalActivity->konec();
      return ResultOfImportStep::success($activityEnd
        ? $activityEnd->formatDb()
        : null
      );
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return ResultOfImportStep::error(
        sprintf(
          'U aktivity %s je sice konec (%s), ale chybí u ní den.',
          $this->describeActivityByInputValues($activityValues, $originalActivity),
          $activityValues[ExportAktivitSloupce::KONEC]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::KONEC]);
  }

  private function describeActivityByInputValues(array $activityValues, ?\Aktivita $originalActivity): string {
    return $this->describeActivityByValues(
      empty($activityValues[ExportAktivitSloupce::ID_AKTIVITY])
        ? null
        : (int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY],
      $activityValues[ExportAktivitSloupce::NAZEV] ?? null,
      $activityValues[ExportAktivitSloupce::URL] ?? null,
      $activityValues[ExportAktivitSloupce::KRATKA_ANOTACE] ?? null,
      $originalActivity
    );
  }

  private function describeActivityBySqlMappedValues(array $remappedValues, ?\Aktivita $originalActivity): string {
    return $this->describeActivityByValues(
      $remappedValues[AktivitaSqlSloupce::ID_AKCE] ?? null,
      $remappedValues[AktivitaSqlSloupce::NAZEV_AKCE] ?? null,
      $remappedValues[AktivitaSqlSloupce::URL_AKCE] ?? null,
      $remappedValues[AktivitaSqlSloupce::POPIS_KRATKY] ?? null,
      $originalActivity
    );
  }

  private function describeActivityByValues(?int $id, ?string $nazev, ?string $url, ?string $kratkaAnotace, ?\Aktivita $originalActivity): string {
    if (!$id && $originalActivity) {
      $id = $originalActivity->id();
    }
    if (!$nazev && $originalActivity) {
      $nazev = $originalActivity->nazev();
    }
    if ($id) {
      $id = (int)$id;
    }
    if ($nazev) {
      $nazev = (string)$nazev;
    }
    if ($id && $nazev) {
      return sprintf("'%s' (%d)", $this->createLinkToActivity($id, $nazev), $id);
    }
    if (!$url && $originalActivity) {
      $url = $originalActivity->urlId();
    }
    if ($nazev && $url) {
      return "'$nazev' s URL '$url'";
    }
    if ($nazev) {
      return $nazev;
    }
    if (!$kratkaAnotace && $originalActivity) {
      $kratkaAnotace = $originalActivity->kratkyPopis();
    }
    return $kratkaAnotace ?: '(bez názvu)';
  }

  private function createDateTimeFromRangeBorder(int $year, string $dayName, string $hoursAndMinutes): ResultOfImportStep {
    try {
      $date = DateTimeGamecon::denKolemZacatkuGameconuProRok($dayName, $year);
    } catch (\Exception $exception) {
      return ResultOfImportStep::error(sprintf("Nepodařilo se vytvořit datum z roku %d, dne '%s' a času '%s'. Chybný formát datumu. Detail: %s", $year, $dayName, $hoursAndMinutes, $exception->getMessage()));
    }

    if (!preg_match('~^(?<hours>\d+)(\s*:\s*(?<minutes>\d+))?$~', $hoursAndMinutes, $timeMatches)) {
      return ResultOfImportStep::error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát času '%s'.", $year, $dayName, $hoursAndMinutes, $hoursAndMinutes));
    }
    $hours = (int)$timeMatches['hours'];
    $minutes = (int)($timeMatches['minutes'] ?? 0);
    $dateTime = $date->setTime($hours, $minutes, 0, 0);
    if (!$dateTime) {
      return ResultOfImportStep::error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát.", $year, $dayName, $hoursAndMinutes));
    }
    return ResultOfImportStep::success($dateTime->formatDb());
  }

  private function getValidatedUrl(array $activityValues, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ResultOfImportStep {
    $activityUrl = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if (!$activityUrl) {
      if ($originalActivity) {
        return ResultOfImportStep::success($originalActivity->urlId());
      }
      if (empty($activityValues[ExportAktivitSloupce::NAZEV])) {
        return ResultOfImportStep::error(sprintf(
          'Nová aktivita %s nemá ani URL, ani název, ze kterého by URL šlo vytvořit.',
          $this->describeActivityByInputValues($activityValues, $originalActivity)
        ));
      }
      $activityUrl = $this->toUrl($activityValues[ExportAktivitSloupce::NAZEV]);
    }
    $activityUrl = $this->toUrl($activityUrl);
    $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2 AND typ = $3
SQL
      ,
      [$activityUrl, $this->currentYear, $singleProgramLine->id()]
    );
    if ($occupiedByActivities) {
      foreach ($occupiedByActivities as $occupiedByActivity) {
        $occupiedByActivityId = (int)$occupiedByActivity['id_akce'];
        $occupiedActivityInstanceId = $occupiedByActivity['patri_pod']
          ? (int)$occupiedByActivity['patri_pod']
          : null;
        if ($this->isIdentifierOccupied($occupiedByActivityId, $occupiedActivityInstanceId, $activityUrl, $singleProgramLine, $originalActivity)) {
          return ResultOfImportStep::error(sprintf(
            "URL '%s'%s %s aktivity %s už je obsazena jinou existující aktivitou %s.",
            $activityUrl,
            empty($activityValues[ExportAktivitSloupce::URL])
              ? ' (odhadnutá z názvu)'
              : '',
            $originalActivity
              ? 'upravované'
              : 'nové',
            $this->describeActivityByInputValues($activityValues, $originalActivity),
            $this->describeActivityById($occupiedByActivityId)
          ));
        }
      }
    }
    return ResultOfImportStep::success($activityUrl);
  }

  private function isIdentifierOccupied(int $occupiedByActivityId, ?int $occupiedActivityInstanceId, ?string $activityUrl, \Typ $singleProgramLine, ?\Aktivita $originalActivity): bool {
    return (!$originalActivity
        || ($occupiedByActivityId !== $originalActivity->id() && (!$occupiedActivityInstanceId || $occupiedActivityInstanceId !== $originalActivity->patriPod()))
        || ($occupiedActivityInstanceId
          && ($parentInstanceId = $this->findParentInstanceId($originalActivity))
          && $occupiedActivityInstanceId != $parentInstanceId
        )
      )
      && !$this->findNewInstanceParentActivityId($activityUrl, $singleProgramLine->id());
  }

  private function toUrl(string $value): string {
    $sanitized = strtolower(odstranDiakritiku($value));
    return preg_replace('~\W+~', '-', $sanitized);
  }

  private function getValidatedActivityName(array $activityValues, ?string $activityUrl, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ResultOfImportStep {
    $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
    if (!$activityNameValue) {
      return $originalActivity
        ? ResultOfImportStep::success($originalActivity->nazev())
        : ResultOfImportStep::error(sprintf(
          'Chybí název u importované aktivity %s.',
          $this->describeActivityByInputValues($activityValues, $originalActivity)
        ));
    }
    $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2 AND typ = $3 LIMIT 1
SQL
      , [$activityNameValue, $this->currentYear, $singleProgramLine->id()]
    );
    if ($occupiedByActivities) {
      foreach ($occupiedByActivities as $occupiedByActivity) {
        $occupiedByActivityId = (int)$occupiedByActivity['id_akce'];
        $occupiedActivityInstanceId = $occupiedByActivity['patri_pod']
          ? (int)$occupiedByActivity['patri_pod']
          : null;
        if ($this->isIdentifierOccupied($occupiedByActivityId, $occupiedActivityInstanceId, $activityUrl, $singleProgramLine, $originalActivity)) {
          return ResultOfImportStep::error(sprintf(
            "Název '%s' %s už je obsazený jinou existující aktivitou %s.",
            $activityNameValue,
            $originalActivity
              ? sprintf('upravované aktivity %s', $this->describeActivity($originalActivity))
              : 'nové aktivity',
            $this->describeActivityById((int)$occupiedByActivityId)
          ));
        }
      }
    }
    return ResultOfImportStep::success($activityNameValue);
  }

  private function getValidatedProgramLineId(array $activityValues, ?\Aktivita $originalActivity): ResultOfImportStep {
    $programLineValue = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if ((string)$programLineValue === '') {
      return $originalActivity
        ? ResultOfImportStep::success($originalActivity->typId())
        : ResultOfImportStep::error(sprintf('Chybí programová linie u aktivity %s.', $this->describeActivityByInputValues($activityValues, null)));
    }
    $programLine = $this->getProgramLineFromValue((string)$programLineValue);
    return $programLine
      ? ResultOfImportStep::success($programLine->id())
      : ResultOfImportStep::error(sprintf(
        "Neznámá programová linie '%s' u aktivity %s.",
        $programLineValue,
        $this->describeActivityByInputValues($activityValues, null)
      ));
  }

  private function getProgramLineFromValue(string $programLineValue): ?\Typ {
    $programLineInt = (int)$programLineValue;
    if ($programLineInt > 0) {
      return $this->getProgramLineById($programLineInt);
    }
    return $this->getProgramLineByName($programLineValue);
  }

  private function getProgramLineById(int $id): ?\Typ {
    return $this->getProgramLinesCache()['id'][$id] ?? null;
  }

  private function getProgramLineByName(string $name): ?\Typ {
    return $this->getProgramLinesCache()['keyFromName'][self::toUnifiedKey($name, [])] ?? null;
  }

  private function getProgramLinesCache(): array {
    if (!$this->programLinesCache) {
      $this->programLinesCache = ['id' => [], 'keyFromName' => []];
      $programLines = \Typ::zVsech();
      foreach ($programLines as $programLine) {
        $this->programLinesCache['id'][$programLine->id()] = $programLine;
        $keyFromName = self::toUnifiedKey($programLine->nazev(), array_keys($this->programLinesCache['keyFromName']));
        $this->programLinesCache['keyFromName'][$keyFromName] = $programLine;
      }
    }
    return $this->programLinesCache;
  }

  private const UNIFY_UP_TO_WHITESPACES = 1;
  private const UNIFY_UP_TO_CASE = 2;
  private const UNIFY_UP_TO_SPACES = 3;
  private const UNIFY_UP_TO_WORD_CHARACTERS = 4;
  private const UNIFY_UP_TO_DIACRITIC = 5;
  private const UNIFY_UP_TO_NUMBERS_AND_LETTERS = 6;
  private const UNIFY_UP_TO_LETTERS = 7;

  private static function toUnifiedKey(
    string $value,
    array $occupiedKeys,
    int $unifyDepth = self::UNIFY_UP_TO_NUMBERS_AND_LETTERS
  ): string {
    $unifiedKey = self::createUnifiedKey($value, $unifyDepth);
    if (in_array($unifiedKey, $occupiedKeys, true)) {
      throw new DuplicatedUnifiedKeyException(
        sprintf(
          "Can not create unified key from '%s' as resulting key '%s' using unify depth %d already exists. Existing keys: %s",
          $value,
          $unifiedKey,
          $unifyDepth,
          implode(';', array_map(static function (string $occupiedKey) {
            return "'$occupiedKey'";
          }, $occupiedKeys))
        ),
        $unifiedKey
      );
    }
    return $unifiedKey;
  }

  private static function createUnifiedKey(string $value, int $depth): string {
    if ($depth <= 0) {
      return $value;
    }
    $value = preg_replace('~\s+~', ' ', $value);
    if ($depth === self::UNIFY_UP_TO_WHITESPACES) {
      return $value;
    }
    $value = mb_strtolower($value, 'UTF-8');
    if ($depth === self::UNIFY_UP_TO_CASE) {
      return $value;
    }
    $value = (string)str_replace(' ', '', $value);
    if ($depth === self::UNIFY_UP_TO_SPACES) {
      return $value;
    }
    $value = preg_replace('~\W~u', '', $value);
    if ($depth === self::UNIFY_UP_TO_WORD_CHARACTERS) {
      return $value;
    }
    $value = odstranDiakritiku($value);
    if ($depth === self::UNIFY_UP_TO_DIACRITIC) {
      return $value;
    }
    $value = preg_replace('~[^a-z0-9]~', '', $value);
    if ($depth === self::UNIFY_UP_TO_NUMBERS_AND_LETTERS) {
      return $value;
    }
    $value = preg_replace('~[^a-z]~', '', $value);
    if ($depth === self::UNIFY_UP_TO_LETTERS) {
      return $value;
    }
    return $value;
  }

  private function getLinkToActivity(\Aktivita $activity): string {
    return $this->createLinkToActivity($activity->id(), $this->describeActivityByInputValues([], $activity));
  }

  private function createLinkToActivity(int $id, string $name): string {
    return sprintf('<a target="_blank" href="%s%d">%s</a>', $this->editActivityUrlSkeleton, $id, $name);
  }
}
