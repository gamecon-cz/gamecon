<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Vyjimkovac\Logovac;

readonly class ActivityImporter
{
    public function __construct(
        private ImportValuesDescriber        $importValuesDescriber,
        private ImportSqlMappedValuesChecker $importValuesChecker,
        private ActivityImagesImporter       $imagesImporter,
        private int                          $currentYear,
        private Logovac                      $logovac
    ) {
    }

    public function importActivity(
        array       $sqlMappedValues,
        ?string     $longAnnotation,
        array       $storytellersIds,
        array       $tagIds,
        TypAktivity $singleProgramLine,
        array       $potentialImageUrls,
        ?Aktivita   $originalActivity
    ): ImportStepResult {
        $checkBeforeSaveResult = $this->importValuesChecker->checkBeforeSave(
            $sqlMappedValues,
            $longAnnotation,
            $tagIds,
            $storytellersIds,
            $singleProgramLine,
            $potentialImageUrls,
            $originalActivity
        );
        if ($checkBeforeSaveResult->isError()) {
            return ImportStepResult::error($checkBeforeSaveResult->getError());
        }

        ['values' => $sqlMappedValues, 'availableStorytellerIds' => $availableStorytellerIds, 'checkResults' => $checkResults] = $checkBeforeSaveResult->getSuccess();

        /** @var Aktivita $importedActivity */
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

    private function saveActivity(
        array       $sqlMappedValues,
        ?string     $longAnnotation,
        array       $storytellersIds,
        array       $tagIds,
        array       $potentialImageUrls,
        TypAktivity $singleProgramLine
    ): ImportStepResult {
        try {
            if (empty($sqlMappedValues[ActivitiesImportSqlColumn::ID_AKCE])) {
                $newInstanceParentActivityId = $this->findParentActivityId($sqlMappedValues[ActivitiesImportSqlColumn::URL_AKCE], $singleProgramLine);
                if ($newInstanceParentActivityId) {
                    $newInstance = $this->createInstanceForParentActivity($newInstanceParentActivityId);
                    $sqlMappedValues[ActivitiesImportSqlColumn::ID_AKCE] = $newInstance->id();
                    $sqlMappedValues[ActivitiesImportSqlColumn::PATRI_POD] = $newInstance->patriPod();
                }
            }
            $savedActivity = Aktivita::uloz($sqlMappedValues, $longAnnotation, $storytellersIds, $tagIds);
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
        return Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
    }

    private function createInstanceForParentActivity(int $parentActivityId): Aktivita {
        return ImportModelsFetcher::fetchActivity($parentActivityId)->instancuj();
    }

}
