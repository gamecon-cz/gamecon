<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
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
  /**
   * @var string
   */
  private $baseUrl;
  /**
   * @var ImportAccessibilityChecker
   */
  private $importAccessibilityChecker;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    int $currentYear,
    \DateTimeInterface $now,
    string $editActivityUrlSkeleton,
    ImportAccessibilityChecker $importAccessibilityChecker,
    string $storytellersPermissionsUrl,
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
    $importObjectsContainer = new ImportObjectsContainer(new ImportUsersCache());

    $this->importValuesReader = new ImportValuesReader($googleSheetsService, $logovac);
    $this->imagesImporter = new ImagesImporter($baseUrl, $importValuesDescriber);
    $this->importValuesValidator = new ImportValuesValidator($importValuesDescriber, $importObjectsContainer, $this->currentYear, $storytellersPermissionsUrl);
    $this->importValuesDescriber = $importValuesDescriber;
    $this->importObjectsContainer = $importObjectsContainer;
    $this->baseUrl = $baseUrl;
    $this->importAccessibilityChecker = $importAccessibilityChecker;
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
        $validatedValuesResult = $this->importValuesValidator->validateValues($singleProgramLine, $activityValues);
        if ($validatedValuesResult->isError()) {
          $errorMessage = $this->getErrorMessageWithSkippedActivityNote($validatedValuesResult);
          $result->addErrorMessage($errorMessage);
          continue;
        }
        if ($validatedValuesResult->hasWarnings()) {
          $result->addWarnings($validatedValuesResult);
        }
        if ($validatedValuesResult->hasErrorLikeWarnings()) {
          $result->addErrorLikeWarnings($validatedValuesResult);
        }
        $validatedValues = $validatedValuesResult->getSuccess();
        unset($validatedValuesResult);
        [
          'values' => $values,
          'originalActivity' => $originalActivity,
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
      $result->addErrorMessage(<<<HTML
Něco se <a href="{$this->baseUrl}/admin/web/chyby" target="_blank">nepovedlo</a>. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.
HTML
      );
      $this->logovac->zaloguj($exception);
      $this->releaseExclusiveLock();
      return $result;
    }
    $savingImagesResult = $this->imagesImporter->saveImages($potentialImageUrlsPerActivity);
    if ($savingImagesResult->hasWarnings()) {
      $result->addWarnings($savingImagesResult);
    }
    if ($savingImagesResult->hasErrorLikeWarnings()) {
      $result->addErrorLikeWarnings($savingImagesResult);
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

  private function findParentActivityId(string $url, \Typ $singleProgramLine): ?int {
    return \Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
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
          "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'.",
          $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->stav()->nazev()
        ));
      }
      if ($originalActivity->zacatek() && $originalActivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s).",
          $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->zacatek()->formatCasNaMinutyStandard()
        ));
      }
      if ($originalActivity->konec() && $originalActivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s).",
          $this->importValuesDescriber->describeActivity($originalActivity),
          $originalActivity->konec()->formatCasNaMinutyStandard()
        ));
      }
    }

    $storytellersAccessibilityResult = $this->importAccessibilityChecker->checkStorytellersAccessibility(
      $storytellersIds,
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $originalActivity,
      $values
    );
    if ($storytellersAccessibilityResult->isError()) {
      return ImportStepResult::error($storytellersAccessibilityResult->getError());
    }
    $warnings = $storytellersAccessibilityResult->getWarnings();
    $errorLikeWarnings = $storytellersAccessibilityResult->getErrorLikeWarnings();
    $availableStorytellerIds = $storytellersAccessibilityResult->getSuccess();

    $locationAccessibilityResult = $this->importAccessibilityChecker->checkLocationByAccessibility(
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
    $warnings = array_merge($warnings, $locationAccessibilityResult->getWarnings());
    $errorLikeWarnings = array_merge($errorLikeWarnings, $locationAccessibilityResult->getErrorLikeWarnings());

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
    if ($originalActivity) {
      return ImportStepResult::successWithWarnings(
        [
          'message' => sprintf('Upravena existující aktivita %s', $this->importValuesDescriber->describeActivity($importedActivity)),
          'importedActivityId' => $importedActivity->id(),
        ],
        $warnings,
        $errorLikeWarnings
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
        $warnings,
        $errorLikeWarnings
      );
    }
    return ImportStepResult::successWithWarnings(
      [
        'message' => sprintf('Nahrána nová aktivita %s', $this->importValuesDescriber->describeActivity($importedActivity)),
        'importedActivityId' => $importedActivity->id(),
      ],
      $warnings,
      $errorLikeWarnings
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

  private function saveActivity(
    array $values,
    ?string $longAnnotation,
    array $storytellersIds,
    array $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ImportStepResult {
    try {
      if (!$values[AktivitaSqlSloupce::ID_AKCE]) {
        $newInstanceParentActivityId = $this->findParentActivityId($values[AktivitaSqlSloupce::URL_AKCE], $singleProgramLine);
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
        '%s: aktivitu se nepodařilo uložit: %s.',
        $this->importValuesDescriber->describeActivityByInputValues($values, $originalActivity),
        $exception->getMessage()
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
