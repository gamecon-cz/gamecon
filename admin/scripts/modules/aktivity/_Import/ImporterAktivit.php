<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;

class ImporterAktivit
{

  /**
   * @var GoogleDriveService
   */
  private $googleDriveService;
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
  private $originalActivities = [];
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
   * @var ImportValuesReader
   */
  private $importValuesReader;
  /**
   * @var ImagesImporter
   */
  private $imagesImporter;
  /**
   * @var ImportValuesDescriber
   */
  private $importValuesDescriber;
  /**
   * @var ImportValuesValidator
   */
  private $importValuesValidator;
  /**
   * @var ImportObjectsContainer
   */
  private $importObjectsContainer;

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
    $this->currentYear = $currentYear;
    $this->now = $now;
    $this->logovac = $logovac;
    $this->mutexPattern = $mutexPattern;

    $importValuesDescriber = new ImportValuesDescriber($editActivityUrlSkeleton);
    $importObjectsContainer = new ImportObjectsContainer();

    $this->importValuesReader = new ImportValuesReader($googleSheetsService, $logovac);
    $this->imagesImporter = new ImagesImporter($baseUrl, $importValuesDescriber);
    $this->importValuesValidator = new ImportValuesValidator($importValuesDescriber, $importObjectsContainer, $this->currentYear);
    $this->importValuesDescriber = $importValuesDescriber;
    $this->importObjectsContainer = $importObjectsContainer;
  }

  public function importujAktivity(string $spreadsheetId): ActivitiesImportResult {
    $result = new ActivitiesImportResult();
    try {
      $processedFileNameResult = $this->getProcessedFileName($spreadsheetId);
      if ($processedFileNameResult->isError()) {
        $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $processedFileNameResult->getError()));
        return $result;
      }
      $processedFileName = $processedFileNameResult->getSuccess();
      unset($processedFileNameResult);
      $result->setProcessedFilename($processedFileName);

      $activitiesValuesResult = $this->importValuesReader->getIndexedValues($spreadsheetId);
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
        $originalActivity = null;
        $originalActivityResult = $this->getValidatedOriginalActivity($activityValues);
        if ($originalActivityResult->isError()) {
          $errorMessage = $this->getErrorMessageWithSkippedActivityNote($originalActivityResult);
          $result->addErrorMessage($errorMessage);
          continue;
        }
        $originalActivity = $originalActivityResult->getSuccess();

        $validatedValuesResult = $this->importValuesValidator->validateValues($singleProgramLine, $activityValues, $originalActivity);
        if ($validatedValuesResult->isError()) {
          $errorMessage = $this->getErrorMessageWithSkippedActivityNote($validatedValuesResult);
          $result->addErrorMessage($errorMessage);
          continue;
        }
        if ($validatedValuesResult->hasWarnings()) {
          $result->addWarningMessages($validatedValuesResult->getWarnings());
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
    } catch (\Exception $exception) {
      $result->addErrorMessage('Něco se nepovedlo. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.');
      $this->logovac->zaloguj($exception);
      $this->releaseExclusiveLock();
      return $result;
    }
    $savingImagesResult = $this->imagesImporter->saveImages($potentialImageUrlsPerActivity);
    if ($savingImagesResult->hasWarnings()) {
      $result->addWarningMessages($savingImagesResult->getWarnings());
    }
    $this->releaseExclusiveLock();
    return $result;
  }


  private function getProcessedFileName(string $spreadsheetId): ImportStepResult {
    try {
      $filename = $this->googleDriveService->getFileName($spreadsheetId);
    } catch (GoogleConnectionException | \Google_Service_Exception $connectionException) {
      $this->logovac->zaloguj($connectionException);
      return ImportStepResult::error('Google Sheets API je dočasně nedostupné. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.');
    }
    if ($filename === null) {
      return ImportStepResult::error(sprintf("Žádný soubor nebyl na Google API nalezen pod ID '$spreadsheetId'."));
    }
    return ImportStepResult::success($filename);
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

  private function findNewInstanceParentActivityId(?string $url, int $programLineId): ?int {
    if (!$url) {
      return null;
    }
    return \Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $programLineId);
  }

  private function getValidatedOriginalActivity(array $activityValues): ImportStepResult {
    $originalActivityIdResult = $this->getActivityId($activityValues);
    if ($originalActivityIdResult->isError()) {
      return ImportStepResult::error($originalActivityIdResult->getError());
    }
    $originalActivityId = $originalActivityIdResult->getSuccess();
    if (!$originalActivityId) {
      return ImportStepResult::success(null);
    }
    $originalActivity = $this->findOriginalActivity($originalActivityId);
    if ($originalActivity) {
      return ImportStepResult::success($originalActivity);
    }
    return ImportStepResult::error(sprintf('Aktivita s ID %d neexistuje. Nelze ji proto importem upravit.', $originalActivityId));
  }

  private function findOriginalActivity(int $id): ?\Aktivita {
    if (!array_key_exists($id, $this->originalActivities)) {
      $activity = \Aktivita::zId($id);
      if (!$activity) {
        return null;
      }
      $this->originalActivities[$id] = $activity;
    }
    return $this->originalActivities[$id];
  }

  private function getActivityId(array $activityValues): ImportStepResult {
    if ($activityValues[ExportAktivitSloupce::ID_AKTIVITY]) {
      return ImportStepResult::success((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
    }
    return ImportStepResult::success(null);
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

  private function importActivity(
    $values,
    $longAnnotation,
    $storytellersIds,
    $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ImportStepResult {
    if ($originalActivity) {
      if (!$originalActivity->bezpecneEditovatelna()) {
        return ImportStepResult::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'",
          $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->stav()->nazev()
        ));
      }
      if ($originalActivity->zacatek() && $originalActivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s)",
          $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->zacatek()->formatCasNaMinutyStandard()
        ));
      }
      if ($originalActivity->konec() && $originalActivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s)",
          $this->importValuesDescriber->describeActivity($originalActivity),
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
      return ImportStepResult::error($storytellersAccessibilityResult->getError());
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
      return ImportStepResult::error($locationAccessibilityResult->getError());
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
      return ImportStepResult::error($savedActivityResult->getError());
    }
    $warnings = array_filter(array_merge($storytellersAccessibilityWarnings, $locationAccessibilityWarnings));
    if ($originalActivity) {
      return ImportStepResult::successWithWarnings(
        [
          'message' => sprintf('Upravena existující aktivita %s', $this->importValuesDescriber->describeActivity($importedActivity)),
          'importedActivityId' => $importedActivity->id(),
        ],
        $warnings
      );
    }
    if ($importedActivity->patriPod()) {
      return ImportStepResult::successWithWarnings(
        [
          'message' => sprintf(
            'Nahrána nová aktivita %s jako %d. <strong>instance</strong> k hlavní aktivitě %s.',
            $this->importValuesDescriber->describeActivity($importedActivity),
            $importedActivity->pocetInstanci(),
            $this->importValuesDescriber->describeActivity($importedActivity->patriPodAktivitu())
          ),
          'importedActivityId' => $importedActivity->id(),
        ],
        $warnings
      );
    }
    return ImportStepResult::successWithWarnings(
      [
        'message' => sprintf('Nahrána nová aktivita %s', $this->importValuesDescriber->describeActivity($importedActivity)),
        'importedActivityId' => $importedActivity->id(),
      ],
      $warnings
    );
  }

  private function guardSingleProgramLineOnly(array $activitiesValues, string $processedFileName): ImportStepResult {
    $programLines = [];
    foreach ($activitiesValues as $row) {
      $programLine = null;
      $programLineId = null;
      $programLineValue = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLineValue) {
        $programLine = $this->importObjectsContainer->getProgramLineFromValue((string)$programLineValue);
      }
      if (!$programLine && $row[ExportAktivitSloupce::ID_AKTIVITY]) {
        $activity = ImportModelsFetcher::fetchActivity($row[ExportAktivitSloupce::ID_AKTIVITY]);
        if ($activity && $activity->typ()) {
          $programLine = $activity->typ();
        }
      }
      if ($programLine && !array_key_exists($programLine->id(), $programLines)) {
        $programLines[$programLineId] = $programLine;
      }
    }
    if (count($programLines) > 1) {
      return ImportStepResult::error(sprintf(
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
      return ImportStepResult::error('V importovaném souboru chybí programová linie, nebo alespoň existující aktivita s nastavenou programovou linií.');
    }
    return ImportStepResult::success(reset($programLines));
  }

  private function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?\Aktivita $originalActivity, array $values): ImportStepResult {
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return ImportStepResult::success($storytellersIds);
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
      return ImportStepResult::success($storytellersIds);
    }
    $warnings = [];
    foreach ($conflictingStorytellers as $conflictingStorytellerId => $implodedActivityIds) {
      $activityIds = explode(',', $implodedActivityIds);
      $warnings[] = sprintf(
        'Vypravěč %s je v čase od %s do %s na %s %s. K aktivitě %s nebyl přiřazen.',
        $this->importValuesDescriber->describeUserById((int)$conflictingStorytellerId),
        $zacatek->formatCasStandard(),
        $konec->formatCasStandard(),
        count($activityIds) === 1
          ? 'aktivitě'
          : 'aktivitách',
        implode(' a ', array_map(function ($activityId) {
          return $this->importValuesDescriber->describeActivityById((int)$activityId);
        }, $activityIds)),
        $this->importValuesDescriber->describeActivityBySqlMappedValues($values, $originalActivity)
      );
    }
    return ImportStepResult::successWithWarnings(array_diff($storytellersIds, array_keys($occupiedStorytellers)), $warnings);
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
  ): ImportStepResult {
    if ($locationId === null) {
      return ImportStepResult::success(null);
    }
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return ImportStepResult::success(true);
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
      return ImportStepResult::success($locationId);
    }
    $currentActivity = $currentActivityId
      ? ImportModelsFetcher::fetchActivity($currentActivityId)
      : null;
    $currentActivityLocation = $currentActivity
      ? $currentActivity->lokace()
      : null;
    return ImportStepResult::successWithWarnings(
      true,
      [
        sprintf(
          'Místnost %s je někdy mezi %s a %s již zabraná jinou aktivitou %s. Nahrávaná aktivita %s byla proto %s.',
          $this->importValuesDescriber->describeLocationById($locationId),
          $zacatek->formatCasNaMinutyStandard(),
          $konec->formatCasNaMinutyStandard(),
          $this->importValuesDescriber->describeActivityById((int)$locationOccupyingActivityId),
          $this->importValuesDescriber->describeActivityBySqlMappedValues($values, $currentActivity),
          $currentActivityLocation
            ? sprintf('ponechána v původní místnosti %s', $currentActivityLocation->nazev())
            : 'nahrána <strong>bez</strong> místnosti'
        ),
      ]
    );
  }

  private function saveActivity(
    array $values,
    ?string $longAnnotation,
    array $storytellersIds,
    array $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ImportStepResult {
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
      return ImportStepResult::success($savedActivity);
    } catch (\Exception $exception) {
      $this->logovac->zaloguj($exception);
      return ImportStepResult::error(sprintf(
        'Nepodařilo se uložit aktivitu %s: %s',
        $this->importValuesDescriber->describeActivityByInputValues($values, $originalActivity), $exception->getMessage()
      ));
    }
  }

  private function createInstanceForParentActivity(int $parentActivityId): \Aktivita {
    $parentActivity = ImportModelsFetcher::fetchActivity($parentActivityId);
    return $parentActivity->instancuj();
  }

  private function getErrorMessageWithSkippedActivityNote(ImportStepResult $resultOfImportStep): string {
    if (!$resultOfImportStep->isError()) {
      throw new \LogicException('Result of import step should be an error, got ' . $this->getResultTypeName($resultOfImportStep));
    }
    return sprintf('%s Aktivita byla <strong>přeskočena</strong>.', $resultOfImportStep->getError());
  }

  private function getResultTypeName(ImportStepResult $resultOfImportStep): string {
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

}
