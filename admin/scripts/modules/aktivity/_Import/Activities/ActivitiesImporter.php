<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\ActivitiesImportException;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;

class ActivitiesImporter
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
     * @var ImportValuesDescriber
     */
    private $importValuesDescriber;
    /**
     * @var ImportValuesReader
     */
    private $importValuesReader;
    /**
     * @var ImportValuesSanitizer
     */
    private $importValuesSanitizer;
    /**
     * @var ImportRequirementsGuardian
     */
    private $importRequirementsGuardian;
    /**
     * @var ActivityImporter
     */
    private $activityImporter;
    /**
     * @var string
     */
    private $errorsListUrl;
    /**
     * @var ActivitiesImportLogger
     */
    private $activitiesImportLogger;
    /**
     * @var ExportAktivitSloupce
     */
    private $exportAktivitSloupce;
    /**
     * @var DateTimeCz
     */
    private $dateTimeCz;

    public function __construct(
        int                    $userId,
        GoogleDriveService     $googleDriveService,
        GoogleSheetsService    $googleSheetsService,
        string                 $editActivityUrlSkeleton,
        \DateTimeInterface     $now,
        string                 $storytellersPermissionsUrl,
        Logovac                $logovac,
        Mutex                  $mutexPattern,
        string                 $errorsListUrl,
        ActivitiesImportLogger $activitiesImportLogger,
        ExportAktivitSloupce   $exportAktivitSloupce,
        DateTimeCz             $dateTimeCz
    ) {
        $this->userId = $userId;
        $this->googleDriveService = $googleDriveService;
        $this->logovac = $logovac;
        $this->mutexPattern = $mutexPattern;

        $currentYear = (int)$now->format('Y');

        $importValuesDescriber = new ImportValuesDescriber($editActivityUrlSkeleton);
        $importObjectsContainer = new ImportObjectsContainer(new ImportUsersCache());
        $importAccessibilityChecker = new ImportSqlMappedValuesChecker($currentYear, $now, $importValuesDescriber);

        $this->importValuesDescriber = $importValuesDescriber;
        $this->importValuesReader = new ImportValuesReader($googleSheetsService, $logovac);
        $this->importValuesSanitizer = new ImportValuesSanitizer($importValuesDescriber, $importObjectsContainer, $currentYear, $storytellersPermissionsUrl);
        $this->importRequirementsGuardian = new ImportRequirementsGuardian($importObjectsContainer);
        $imagesImporter = new ActivityImagesImporter($importValuesDescriber, $this->logovac);
        $this->activityImporter = new ActivityImporter($importValuesDescriber, $importAccessibilityChecker, $imagesImporter, $currentYear, $logovac);
        $this->errorsListUrl = $errorsListUrl;
        $this->activitiesImportLogger = $activitiesImportLogger;
        $this->exportAktivitSloupce = $exportAktivitSloupce;
        $this->dateTimeCz = $dateTimeCz;
    }

    public function importActivities(string $spreadsheetId): ActivitiesImportResult {
        $result = new ActivitiesImportResult();
        try {
            $processedFileNameResult = $this->getProcessedFileName($spreadsheetId);
            if ($processedFileNameResult->isError()) {
                $result->addErrorMessage($processedFileNameResult->getError(), null);
                return $result;
            }
            $processedFileName = $processedFileNameResult->getSuccess();
            unset($processedFileNameResult);
            $result->setProcessedFilename($processedFileName);

            $activitiesValuesResult = $this->importValuesReader->getIndexedValues($spreadsheetId);
            if ($activitiesValuesResult->isError()) {
                $result->addErrorMessage($activitiesValuesResult->getError(), null);
                return $result;
            }
            $activitiesValues = $activitiesValuesResult->getSuccess();
            unset($activitiesValuesResult);

            $activitiesValues = $this->sortActivitiesToHaveLatestFirst($activitiesValues);

            $typAktivityResult = $this->importRequirementsGuardian->guardSingleProgramLineOnly($activitiesValues, $processedFileName);
            if ($typAktivityResult->isError()) {
                $result->addErrorMessage($typAktivityResult->getError(), null);
                return $result;
            }
            /** @var TypAktivity $typAktivity */
            $typAktivity = $typAktivityResult->getSuccess();
            unset($typAktivityResult);

            if (!$this->getExclusiveLock($typAktivity->nazev())) {
                $result->addErrorMessage(
                    sprintf(
                        "Právě probíhá jiný import aktivit z programové linie '%s'. Zkus to za chvíli znovu.",
                        mb_ucfirst($typAktivity->nazev())
                    ),
                    null
                );
                return $result;
            }

            if (defined('IMPOR_AKTIVIT_JENOM_JAKO') && IMPOR_AKTIVIT_JENOM_JAKO) {
                dbBegin();
            }
            foreach ($activitiesValues as $activityValues) {
                $activityGuid = uniqid('importActivity', true);

                $validatedValuesResult = $this->importValuesSanitizer->sanitizeValuesToImport($typAktivity, $activityValues);
                $result->addWarnings($validatedValuesResult, $activityGuid);
                $result->addErrorLikeWarnings($validatedValuesResult, $activityGuid);
                if ($validatedValuesResult->isError()) {
                    $result->addErrorMessage($validatedValuesResult->getError(), $activityGuid);
                    $activityFinalDescription = $validatedValuesResult->getLastActivityDescription()
                        ?? $this->importValuesDescriber->describeActivityByInputValues($activityValues, null);
                    $result->solveActivityDescription($activityGuid, $activityFinalDescription);
                    continue;
                }
                $validatedValues = $validatedValuesResult->getSuccess();
                unset($validatedValuesResult);
                [
                    'values' => $sqlMappedValues,
                    'originalActivity' => $originalActivity,
                    'longAnnotation' => $longAnnotation,
                    'storytellersIds' => $storytellersIds,
                    'tagIds' => $tagIds,
                    'potentialImageUrls' => $potentialImageUrls,
                ] = $validatedValues;

                $importActivityResult = $this->activityImporter->importActivity(
                    $sqlMappedValues,
                    $longAnnotation,
                    $storytellersIds,
                    $tagIds,
                    $typAktivity,
                    $potentialImageUrls,
                    $originalActivity
                );
                $result->addWarnings($importActivityResult, $activityGuid);
                $result->addErrorLikeWarnings($importActivityResult, $activityGuid);
                if ($importActivityResult->isError()) {
                    $result->addErrorMessage($importActivityResult->getError(), $activityGuid);
                    $activityFinalDescription = $this->importValuesDescriber->describeActivityBySqlMappedValues($sqlMappedValues, $originalActivity);
                    $result->solveActivityDescription($activityGuid, $activityFinalDescription);
                    continue;
                }
                /** @var Aktivita $importedActivity */
                ['message' => $successMessage, 'importedActivity' => $importedActivity] = $importActivityResult->getSuccess();
                if ($result->wasProblemWith($activityGuid)) {
                    $result->addWarningMessage($successMessage . ' Import ale nebyl bez problémů, viz výše.', $activityGuid);
                } else {
                    $result->addSuccessMessage($successMessage, $activityGuid);
                }
                unset($importActivityResult);

                $result->incrementImportedCount();

                $activityFinalDescription = $this->importValuesDescriber->describeActivity($importedActivity);
                $result->solveActivityDescription($activityGuid, $activityFinalDescription);
            }
        } catch (\Exception $exception) {
            $result->addErrorMessage(<<<HTML
Něco se <a href="{$this->errorsListUrl}" target="_blank">nepovedlo</a>. Zkus to za chvíli znovu.
HTML
                , null
            );
            $this->logovac->zaloguj($exception);
            $this->releaseExclusiveLock();
            return $result;
        }
        if ((!defined('POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU')
                || !POVOLEN_OPAKOVANY_IMPORT_AKTIVIT_ZE_STEJNEHO_SOUBORU)
            && $result->getImportedCount() > 0
        ) {
            $this->activitiesImportLogger->logUsedSpreadsheet($this->userId, $spreadsheetId, new \DateTimeImmutable());
        }
        $this->releaseExclusiveLock();

        return $result;
    }

    /**
     * To import newest, "children" activities first and previous, "parent" later
     * to support linking of parent-to-child-not-yet-in-system activities.
     * @param array $activitiesValues
     * @return array $activitiesValues
     */
    private function sortActivitiesToHaveLatestFirst(array $activitiesValues): array {
        usort($activitiesValues, function (array $someActivityValues, array $anotherActivityValues) {
            $someActivityTime = $this->getActivityTimeForSort($someActivityValues);
            $anotherActivityTime = $this->getActivityTimeForSort($anotherActivityValues);
            return $anotherActivityTime <=> $someActivityTime; // latest top, earlier bottom
        });

        return $activitiesValues;
    }

    private function getActivityTimeForSort(array $activityValues): int {
        if (empty($activityValues[$this->exportAktivitSloupce::DEN])) {
            return PHP_INT_MAX;
        }
        try {
            $dayTimeForSort = $this->dateTimeCz::poradiDne($activityValues[$this->exportAktivitSloupce::DEN]) * 24;
        } catch (\RuntimeException $runtimeException) {
            return PHP_INT_MAX; // invalid date name
        }
        if (!empty($activityValues[$this->exportAktivitSloupce::ZACATEK])) {
            return $dayTimeForSort + $activityValues[$this->exportAktivitSloupce::ZACATEK];
        }
        if (!empty($activityValues[$this->exportAktivitSloupce::KONEC])) {
            return $dayTimeForSort + $activityValues[$this->exportAktivitSloupce::KONEC];
        }
        return $dayTimeForSort;
    }

    public function __destruct() {
        if (defined('IMPOR_AKTIVIT_JENOM_JAKO') && IMPOR_AKTIVIT_JENOM_JAKO) {
            dbRollback();
        }
    }

    private function getProcessedFileName(string $spreadsheetId): ImportStepResult {
        try {
            $filename = $this->googleDriveService->getFileName($spreadsheetId);
        } catch (GoogleConnectionException|\Google_Service_Exception $connectionException) {
            $this->logovac->zaloguj($connectionException);
            return ImportStepResult::error('Google Sheets API je dočasně nedostupné. Zkus to za chvíli znovu.');
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
        if (!$this->hasMutexForProgramLine()) {
            return;
        }
        $this->getMutexForProgramLine()->odemkni($this->getMutexKey());
    }

    private function hasMutexForProgramLine() {
        return $this->mutexForProgramLine && $this->mutexForProgramLine->zamceno();
    }

    private function getMutexForProgramLine(): Mutex {
        if (!$this->mutexForProgramLine) {
            throw new ActivitiesImportException('Mutex for imported program line does not exists yet');
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
            throw new ActivitiesImportException('Mutex key is empty');
        }
        return $this->mutexKey;
    }
}
