<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Vyjimkovac\Logovac;

class ActivityImporter
{
    /**
     * @var ImportValuesDescriber
     */
    private $importValuesDescriber;
    /**
     * @var ImportSqlMappedValuesChecker
     */
    private $importValuesChecker;
    /**
     * @var ImagesImporter
     */
    private $imagesImporter;
    /**
     * @var int
     */
    private $currentYear;
    /**
     * @var Logovac
     */
    private $logovac;

    public function __construct(
        ImportValuesDescriber        $importValuesDescriber,
        ImportSqlMappedValuesChecker $importValuesChecker,
        ImagesImporter               $imagesImporter,
        int                          $currentYear,
        Logovac                      $logovac
    ) {
        $this->importValuesDescriber = $importValuesDescriber;
        $this->importValuesChecker = $importValuesChecker;
        $this->imagesImporter = $imagesImporter;
        $this->currentYear = $currentYear;
        $this->logovac = $logovac;
    }

    public function importActivity(
        array       $sqlMappedValues,
        ?string     $longAnnotation,
        array       $storytellersIds,
        array       $tagIds,
        TypAktivity $singleProgramLine,
        array       $potentialImageUrls,
        ?\Aktivita  $originalActivity
    ): ImportStepResult {
        $checkBeforeSaveResult = $this->checkBeforeSave($sqlMappedValues, $longAnnotation, $tagIds, $storytellersIds, $singleProgramLine, $potentialImageUrls, $originalActivity);
        if ($checkBeforeSaveResult->isError()) {
            return ImportStepResult::error($checkBeforeSaveResult->getError());
        }

        ['values' => $sqlMappedValues, 'availableStorytellerIds' => $availableStorytellerIds, 'checkResults' => $checkResults] = $checkBeforeSaveResult->getSuccess();

        /** @var  \Aktivita $importedActivity */
        $savedActivityResult = $this->saveActivity(
            $sqlMappedValues,
            $longAnnotation,
            $availableStorytellerIds,
            $tagIds,
            $potentialImageUrls,
            $singleProgramLine
        );
        $importedActivity = $savedActivityResult->getSuccess();

        if ($savedActivityResult->isError()) {
            return ImportStepResult::error($savedActivityResult->getError());
        }
        $checkResults[] = $savedActivityResult;
        unset($savedActivityResult);

        ['warnings' => $warnings, 'errorLikeWarnings' => $errorLikeWarnings] = ImportStepResult::collectWarningsFromSteps($checkResults);

        if ($originalActivity) {
            return ImportStepResult::successWithWarnings(
                [
                    'message' => sprintf('Upravena existující %s.', $importedActivity->jeHlavni() ? '"mateřská" aktivita' : 'instance'),
                    'importedActivity' => $importedActivity,
                ],
                $warnings,
                $errorLikeWarnings
            );
        }
        if ($importedActivity->jeInstance()) {
            return ImportStepResult::successWithWarnings(
                [
                    'message' => sprintf(
                        'Nahrána jako nová, %d. instance k "mateřské" aktivitě %s.',
                        $importedActivity->pocetInstanci(),
                        $this->importValuesDescriber->describeActivity($importedActivity->patriPodAktivitu())
                    ),
                    'importedActivity' => $importedActivity,
                ],
                $warnings,
                $errorLikeWarnings
            );
        }
        return ImportStepResult::successWithWarnings(
            [
                'message' => sprintf('Nahrána jako nová %s.', $importedActivity->jeHlavni() ? '"mateřská" aktivita' : 'instance'),
                'importedActivity' => $importedActivity,
            ],
            $warnings,
            $errorLikeWarnings
        );
    }

    private function checkBeforeSave(array $sqlMappedValues, ?string $longAnnotation, array $tagIds, array $storytellersIds, TypAktivity $singleProgramLine, array $potentialImageUrls, ?\Aktivita $originalActivity): ImportStepResult {
        $checkResults = [];

        $timeResult = $this->importValuesChecker->checkTime($sqlMappedValues, $originalActivity);
        if ($timeResult->isError()) {
            return ImportStepResult::error($timeResult->getError());
        }
        ['start' => $start, 'end' => $end] = $timeResult->getSuccess();
        $sqlMappedValues[AktivitaSqlSloupce::ZACATEK] = $start ?: null;
        $sqlMappedValues[AktivitaSqlSloupce::KONEC] = $end ?: null;
        $checkResults[] = $timeResult;
        unset($timeResult);

        $urlUniquenessResult = $this->importValuesChecker->checkUrlUniqueness($sqlMappedValues, $singleProgramLine, $originalActivity);
        if ($urlUniquenessResult->isError()) {
            return ImportStepResult::error($urlUniquenessResult->getError());
        }
        $checkResults[] = $urlUniquenessResult;
        unset($urlUniquenessResult);

        $nameUniqueness = $this->importValuesChecker->checkNameUniqueness($sqlMappedValues, $singleProgramLine, $originalActivity);
        if ($nameUniqueness->isError()) {
            return ImportStepResult::error($nameUniqueness->getError());
        }
        $checkResults[] = $nameUniqueness;
        unset($nameUniqueness);

        $stateUsabilityResult = $this->importValuesChecker->checkStateUsability($sqlMappedValues, $originalActivity);
        if ($stateUsabilityResult->isError()) {
            return ImportStepResult::error($stateUsabilityResult->getError());
        }
        $sqlMappedValues[AktivitaSqlSloupce::STAV] = $stateUsabilityResult->getSuccess();
        $checkResults[] = $stateUsabilityResult;
        unset($stateUsabilityResult);

        $requiredValuesForStateResult = $this->importValuesChecker->checkRequiredValuesForState($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);
        if ($requiredValuesForStateResult->isError()) {
            return ImportStepResult::error($requiredValuesForStateResult->getError());
        }
        $sqlMappedValues[AktivitaSqlSloupce::STAV] = $requiredValuesForStateResult->getSuccess();
        $checkResults[] = $requiredValuesForStateResult;
        unset($requiredValuesForStateResult);

        $storytellersAccessibilityResult = $this->importValuesChecker->checkStorytellersAccessibility(
            $storytellersIds,
            $sqlMappedValues[AktivitaSqlSloupce::ZACATEK],
            $sqlMappedValues[AktivitaSqlSloupce::KONEC],
            $originalActivity
        );
        if ($storytellersAccessibilityResult->isError()) {
            return ImportStepResult::error($storytellersAccessibilityResult->getError());
        }
        $availableStorytellerIds = $storytellersAccessibilityResult->getSuccess();
        $checkResults[] = $storytellersAccessibilityResult;
        unset($storytellersAccessibilityResult);

        $locationAccessibilityResult = $this->importValuesChecker->checkLocationByAccessibility(
            $sqlMappedValues[AktivitaSqlSloupce::LOKACE],
            $sqlMappedValues[AktivitaSqlSloupce::ZACATEK],
            $sqlMappedValues[AktivitaSqlSloupce::KONEC],
            $originalActivity,
            $singleProgramLine
        );
        if ($locationAccessibilityResult->isError()) {
            return ImportStepResult::error($locationAccessibilityResult->getError());
        }
        $checkResults[] = $locationAccessibilityResult;
        unset($locationAccessibilityResult);

        $teamCapacityRangeResult = $this->importValuesChecker->checkTeamCapacityRange(
            (bool)$sqlMappedValues[AktivitaSqlSloupce::TEAMOVA],
            $sqlMappedValues[AktivitaSqlSloupce::TEAM_MIN],
            $sqlMappedValues[AktivitaSqlSloupce::TEAM_MAX]
        );
        if ($teamCapacityRangeResult->isError()) {
            return ImportStepResult::error($teamCapacityRangeResult->getError());
        }
        $checkResults[] = $teamCapacityRangeResult;
        unset($teamCapacityRangeResult);

        $nonTeamCapacityResult = $this->importValuesChecker->checkNonTeamCapacity(
            (bool)$sqlMappedValues[AktivitaSqlSloupce::TEAMOVA],
            $sqlMappedValues[AktivitaSqlSloupce::TYP] === TypAktivity::TECHNICKA,
            $sqlMappedValues[AktivitaSqlSloupce::KAPACITA],
            $sqlMappedValues[AktivitaSqlSloupce::KAPACITA_M],
            $sqlMappedValues[AktivitaSqlSloupce::KAPACITA_F]
        );
        if ($nonTeamCapacityResult->isError()) {
            return ImportStepResult::error($nonTeamCapacityResult->getError());
        }
        $checkResults[] = $nonTeamCapacityResult;
        unset($teamCapacityRangeResult);

        return ImportStepResult::success(['values' => $sqlMappedValues, 'availableStorytellerIds' => $availableStorytellerIds, 'checkResults' => $checkResults]);
    }

    private function saveActivity(
        array       $sqlMappedValues,
        ?string     $longAnnotation,
        array       $storytellersIds,
        array       $tagIds,
        array       $potentialImageUrls,
        TypAktivity $singleProgramLine
    ): ImportStepResult {
        try {
            if (empty($sqlMappedValues[AktivitaSqlSloupce::ID_AKCE])) {
                $newInstanceParentActivityId = $this->findParentActivityId($sqlMappedValues[AktivitaSqlSloupce::URL_AKCE], $singleProgramLine);
                if ($newInstanceParentActivityId) {
                    $newInstance = $this->createInstanceForParentActivity($newInstanceParentActivityId);
                    $sqlMappedValues[AktivitaSqlSloupce::ID_AKCE] = $newInstance->id();
                    $sqlMappedValues[AktivitaSqlSloupce::PATRI_POD] = $newInstance->patriPod();
                }
            }
            $savedActivity = \Aktivita::uloz($sqlMappedValues, $longAnnotation, $storytellersIds, $tagIds);
            $addImageResult = $this->imagesImporter->addImage($potentialImageUrls, $savedActivity);
            return ImportStepResult::successWithWarnings($savedActivity, $addImageResult->getWarnings(), $addImageResult->getErrorLikeWarnings());
        } catch (\Exception $exception) {
            $this->logovac->zaloguj($exception);
            return ImportStepResult::error(
                sprintf(
                    'Aktivitu %s se nepodařilo uložit: %s.',
                    $this->importValuesDescriber->describeActivityBySqlMappedValues($sqlMappedValues, null),
                    $exception->getMessage()
                )
            );
        }
    }

    private function findParentActivityId(string $url, TypAktivity $singleProgramLine): ?int {
        return \Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
    }

    private function createInstanceForParentActivity(int $parentActivityId): \Aktivita {
        return ImportModelsFetcher::fetchActivity($parentActivityId)->instancuj();
    }

}
