<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;

class ImportValuesSanitizer
{
    /**
     * @var ImportValuesDescriber
     */
    private $importValuesDescriber;
    /**
     * @var int
     */
    private $currentYear;
    /**
     * @var ImportObjectsContainer
     */
    private $importObjectsContainer;
    /**
     * @var string
     */
    private $storytellersPermissionsUrl;

    public function __construct(
        ImportValuesDescriber  $importValuesDescriber,
        ImportObjectsContainer $importObjectsContainer,
        int                    $currentYear,
        string                 $storytellersPermissionsUrl
    ) {
        $this->importValuesDescriber = $importValuesDescriber;
        $this->currentYear = $currentYear;
        $this->importObjectsContainer = $importObjectsContainer;
        $this->storytellersPermissionsUrl = $storytellersPermissionsUrl;
    }

    public function sanitizeValuesToImport(TypAktivity $singleProgramLine, array $inputValues): ImportStepResult {
        $originalActivityResult = $this->getValidatedOriginalActivity($inputValues);
        if ($originalActivityResult->isError()) {
            $inputValuesForDescription = $inputValues;
            // original activity does not exists, so we do not want a link to it (which is created from ID, so we remove it)
            unset($inputValuesForDescription[ExportAktivitSloupce::ID_AKTIVITY]);
            return ImportStepResult::error($originalActivityResult->getError())
                ->setLastActivityDescription(
                    $this->importValuesDescriber->describeActivityByInputValues(
                        $inputValuesForDescription,
                        null
                    )
                );
        }

        $stepsResults = [];

        /** @var Aktivita|null $originalActivity */
        $originalActivity = $originalActivityResult->getSuccess();
        $stepsResults[] = $originalActivityResult;
        unset($originalActivityResult);

        $sanitizedValuesResult = $this->getSanitizedValues($inputValues, $singleProgramLine, $originalActivity);
        if ($sanitizedValuesResult->isError()) {
            return ImportStepResult::error($sanitizedValuesResult->getError())
                ->setLastActivityDescription($this->importValuesDescriber->describeActivityByInputValues($inputValues, $originalActivity));
        }
        [
            'sanitizedValues' => $sanitizedValues,
            'stepsResults' => $sanitizedValuesStepsResults,
            'longAnnotation' => $longAnnotation,
            'tagIds' => $tagIds,
            'activityUrl' => $activityUrl,
            'storytellersIds' => $storytellersIds,
        ] = $sanitizedValuesResult->getSuccess();

        $stepsResults = array_merge($stepsResults, $sanitizedValuesStepsResults);

        $potentialImageUrlsResult = $this->getPotentialImageUrls($inputValues, $activityUrl);
        if ($potentialImageUrlsResult->isError()) {
            return ImportStepResult::error($potentialImageUrlsResult->getError())
                ->setLastActivityDescription($this->importValuesDescriber->describeActivityByInputValues($inputValues, $originalActivity));
        }
        $potentialImageUrls = $potentialImageUrlsResult->getSuccess();
        $stepsResults[] = $potentialImageUrlsResult;
        unset($potentialImageUrlsResult);

        ['warnings' => $warnings, 'errorLikeWarnings' => $errorLikeWarnings] = ImportStepResult::collectWarningsFromSteps($stepsResults);

        return ImportStepResult::successWithWarnings(
            [
                'values' => $sanitizedValues,
                'originalActivity' => $originalActivity,
                'longAnnotation' => $longAnnotation,
                'storytellersIds' => $storytellersIds,
                'tagIds' => $tagIds,
                'potentialImageUrls' => $potentialImageUrls,
            ],
            $warnings,
            $errorLikeWarnings
        )->setLastActivityDescription($this->importValuesDescriber->describeActivityByInputValues($inputValues, $originalActivity));
    }

    private function getSanitizedValues(array $inputValues, TypAktivity $singleProgramLine, ?Aktivita $originalActivity): ImportStepResult {
        $sanitizedValues = $this->getValuesFromOriginalActivity($originalActivity);

        $stepsResults = [];

        $programLineIdResult = $this->getValidatedProgramLineId($inputValues, $singleProgramLine);
        if ($programLineIdResult->isError()) {
            return ImportStepResult::error($programLineIdResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::TYP] = $programLineIdResult->getSuccess();
        $stepsResults[] = $programLineIdResult;
        unset($programLineIdResult);

        $activityUrlResult = $this->getValidatedUrl($inputValues, $originalActivity);
        if ($activityUrlResult->isError()) {
            return ImportStepResult::error($activityUrlResult->getError());
        }
        $activityUrl = $activityUrlResult->getSuccess();
        $sanitizedValues[ActivitiesImportSqlColumn::URL_AKCE] = $activityUrl;
        $stepsResults[] = $activityUrlResult;
        unset($activityUrlResult);

        $parentActivityResult = $this->getValidatedParentActivity($activityUrl, $singleProgramLine);
        if ($parentActivityResult->isError()) {
            return ImportStepResult::error($parentActivityResult->getError());
        }
        $parentActivity = $parentActivityResult->getSuccess();
        $stepsResults[] = $parentActivityResult;
        unset($parentActivityResult);

        $activityNameResult = $this->getValidatedActivityName($inputValues, $originalActivity, $parentActivity);
        if ($activityNameResult->isError()) {
            return ImportStepResult::error($activityNameResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::NAZEV_AKCE] = $activityNameResult->getSuccess();
        $stepsResults[] = $activityNameResult;
        unset($activityNameResult);

        $shortAnnotationResult = $this->getValidatedShortAnnotation($inputValues, $originalActivity, $parentActivity);
        if ($shortAnnotationResult->isError()) {
            return ImportStepResult::error($shortAnnotationResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::POPIS_KRATKY] = $shortAnnotationResult->getSuccess();
        $stepsResults[] = $shortAnnotationResult;
        unset($shortAnnotationResult);

        $tagIdsResult = $this->getValidatedTagIds($inputValues, $originalActivity, $parentActivity);
        if ($tagIdsResult->isError()) {
            return ImportStepResult::error($tagIdsResult->getError());
        }
        $tagIds = $tagIdsResult->getSuccess();
        $stepsResults[] = $tagIdsResult;
        unset($tagIdsResult);

        $longAnnotationResult = $this->getValidatedLongAnnotation($inputValues, $originalActivity, $parentActivity);
        if ($longAnnotationResult->isError()) {
            return ImportStepResult::error($longAnnotationResult->getError());
        }
        $longAnnotation = $longAnnotationResult->getSuccess();
        $stepsResults[] = $longAnnotationResult;
        unset($longAnnotationResult);

        $activityStartResult = $this->getValidatedStart($inputValues, $originalActivity, $parentActivity);
        if ($activityStartResult->isError()) {
            return ImportStepResult::error($activityStartResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::ZACATEK] = $activityStartResult->getSuccess();
        $stepsResults[] = $activityStartResult;
        unset($activityStartResult);

        $activityEndResult = $this->getValidatedEnd($inputValues, $originalActivity, $parentActivity);
        if ($activityEndResult->isError()) {
            return ImportStepResult::error($activityEndResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::KONEC] = $activityEndResult->getSuccess();
        $stepsResults[] = $activityEndResult;
        unset($activityEndResult);

        $locationIdResult = $this->getValidatedLocationId($inputValues, $originalActivity, $parentActivity);
        if ($locationIdResult->isError()) {
            return ImportStepResult::error($locationIdResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::LOKACE] = $locationIdResult->getSuccess();
        $stepsResults[] = $locationIdResult;
        unset($locationIdResult);

        $storytellersIdsResult = $this->getValidatedStorytellersIds($inputValues, $originalActivity, $parentActivity);
        if ($storytellersIdsResult->isError()) {
            return ImportStepResult::error($storytellersIdsResult->getError());
        }
        $storytellersIds = $storytellersIdsResult->getSuccess();
        $stepsResults[] = $storytellersIdsResult;
        unset($storytellersIdsResult);

        $unisexCapacityResult = $this->getValidatedUnisexCapacity($inputValues, $originalActivity, $parentActivity);
        if ($unisexCapacityResult->isError()) {
            return ImportStepResult::error($unisexCapacityResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::KAPACITA] = $unisexCapacityResult->getSuccess();
        $stepsResults[] = $unisexCapacityResult;
        unset($unisexCapacityResult);

        $menCapacityResult = $this->getValidatedMenCapacity($inputValues, $originalActivity, $parentActivity);
        if ($menCapacityResult->isError()) {
            return ImportStepResult::error($menCapacityResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::KAPACITA_M] = $menCapacityResult->getSuccess();
        $stepsResults[] = $menCapacityResult;
        unset($menCapacityResult);

        $womenCapacityResult = $this->getValidatedWomenCapacity($inputValues, $originalActivity, $parentActivity);
        if ($womenCapacityResult->isError()) {
            return ImportStepResult::error($womenCapacityResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::KAPACITA_F] = $womenCapacityResult->getSuccess();
        $stepsResults[] = $womenCapacityResult;
        unset($womenCapacityResult);

        $forTeamResult = $this->getValidatedForTeam($inputValues, $originalActivity, $parentActivity);
        if ($forTeamResult->isError()) {
            return ImportStepResult::error($forTeamResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::TEAMOVA] = $forTeamResult->getSuccess();
        $stepsResults[] = $forTeamResult;
        unset($forTeamResult);

        $minimalTeamCapacityResult = $this->getValidatedMinimalTeamCapacity((bool)$sanitizedValues[ActivitiesImportSqlColumn::TEAMOVA], $inputValues, $originalActivity, $parentActivity);
        if ($minimalTeamCapacityResult->isError()) {
            return ImportStepResult::error($minimalTeamCapacityResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::TEAM_MIN] = $minimalTeamCapacityResult->getSuccess();
        $stepsResults[] = $minimalTeamCapacityResult;
        unset($minimalTeamCapacityResult);

        $maximalTeamCapacityResult = $this->getValidatedMaximalTeamCapacity((bool)$sanitizedValues[ActivitiesImportSqlColumn::TEAMOVA], $inputValues, $originalActivity, $parentActivity);
        if ($maximalTeamCapacityResult->isError()) {
            return ImportStepResult::error($maximalTeamCapacityResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::TEAM_MAX] = $maximalTeamCapacityResult->getSuccess();
        $stepsResults[] = $maximalTeamCapacityResult;
        unset($maximalTeamCapacityResult);

        $childResult = $this->getValidatedChild($inputValues, $originalActivity, $parentActivity);
        if ($childResult->isError()) {
            return ImportStepResult::error($childResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::DITE] = $childResult->getSuccess();
        $stepsResults[] = $childResult;
        unset($priceResult);

        $priceResult = $this->getValidatedPrice($inputValues, $originalActivity, $parentActivity);
        if ($priceResult->isError()) {
            return ImportStepResult::error($priceResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::CENA] = $priceResult->getSuccess();
        $stepsResults[] = $priceResult;
        unset($priceResult);

        $withoutDiscountResult = $this->getValidatedWithoutDiscount($inputValues, $originalActivity, $parentActivity);
        if ($withoutDiscountResult->isError()) {
            return ImportStepResult::error($withoutDiscountResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::BEZ_SLEVY] = $withoutDiscountResult->getSuccess();
        $stepsResults[] = $withoutDiscountResult;
        unset($withoutDiscountResult);

        $equipmentResult = $this->getValidatedEquipment($inputValues, $originalActivity, $parentActivity);
        if ($equipmentResult->isError()) {
            return ImportStepResult::error($equipmentResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::VYBAVENI] = $equipmentResult->getSuccess();
        $stepsResults[] = $equipmentResult;
        unset($equipmentResult);

        $stateIdResult = $this->getValidatedStateId($inputValues, $originalActivity, $parentActivity);
        if ($stateIdResult->isError()) {
            return ImportStepResult::error($stateIdResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::STAV] = $stateIdResult->getSuccess();
        $stepsResults[] = $stateIdResult;
        unset($stateIdResult);

        $yearResult = $this->getValidatedYear($originalActivity, $parentActivity);
        if ($yearResult->isError()) {
            return ImportStepResult::error($yearResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::ROK] = $yearResult->getSuccess();
        $stepsResults[] = $yearResult;
        unset($yearResult);

        $instanceIdResult = $this->getValidatedInstanceId($originalActivity, $parentActivity);
        if ($instanceIdResult->isError()) {
            return ImportStepResult::error($instanceIdResult->getError());
        }
        $sanitizedValues[ActivitiesImportSqlColumn::PATRI_POD] = $instanceIdResult->getSuccess();
        $stepsResults[] = $instanceIdResult;
        unset($instanceIdResult);

        return ImportStepResult::success([
            'sanitizedValues' => $sanitizedValues,
            'stepsResults' => $stepsResults,
            'longAnnotation' => $longAnnotation,
            'tagIds' => $tagIds,
            'activityUrl' => $activityUrl,
            'storytellersIds' => $storytellersIds,
        ]);
    }

    private function getValuesFromOriginalActivity(?Aktivita $originalActivity): array {
        if (!$originalActivity) {
            return [];
        }
        $sanitizedValues = $originalActivity->rawDb();
        // remove values originating in another tables
        $sanitizedValues = array_intersect_key(
            $sanitizedValues,
            array_fill_keys(ActivitiesImportSqlColumn::vsechnySloupce(), true)
        );
        $sanitizedValues[ActivitiesImportSqlColumn::ID_AKCE] = !empty($sanitizedValues[ActivitiesImportSqlColumn::ID_AKCE])
            ? (int)$sanitizedValues[ActivitiesImportSqlColumn::ID_AKCE]
            : null;
        return $sanitizedValues;
    }

    private function getValidatedOriginalActivity(array $activityValues): ImportStepResult {
        $originalActivityId = $this->getActivityIdFromValues($activityValues);
        if (!$originalActivityId) {
            return ImportStepResult::success(null);
        }
        $originalActivity = Aktivita::zId($originalActivityId);
        if ($originalActivity) {
            return ImportStepResult::success($originalActivity);
        }
        return ImportStepResult::error(
            sprintf('Aktivita s ID %d neexistuje. Nelze ji proto importem upravit.', $originalActivityId)
        );
    }

    private function getActivityIdFromValues(array $activityValues): ?int {
        return !empty($activityValues[ExportAktivitSloupce::ID_AKTIVITY])
            ? (int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]
            : null;
    }

    private function getPotentialImageUrls(array $activityValues, string $activityUrl): ImportStepResult {
        $imageUrl = $activityValues[ExportAktivitSloupce::OBRAZEK] ?? null;
        if (!$imageUrl) {
            return ImportStepResult::success([]);
        }
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return ImportStepResult::successWithWarnings([], [sprintf("Rozbité URL obrázku '%s'.", $imageUrl)]);
        }
        if (preg_match('~[.](jpg|png|gif)$~i', $imageUrl)) {
            return ImportStepResult::success([$imageUrl]);
        }
        $imageUrlWithoutExtension = rtrim($imageUrl, '/') . '/' . $activityUrl;
        return ImportStepResult::success([
            $imageUrlWithoutExtension . '.jpg',
            $imageUrlWithoutExtension . '.png',
            $imageUrlWithoutExtension . '.gif',
        ]);
    }

    private function getValidatedStateId(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $stateValue = $activityValues[ExportAktivitSloupce::STAV] ?? null;
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        if ((string)$stateValue === '') {
            return ImportStepResult::success($sourceActivity && $sourceActivity->idStavu() !== null
                ? $sourceActivity->idStavu()
                : StavAktivity::NOVA
            );
        }
        $state = $this->importObjectsContainer->getStateFromValue((string)$stateValue);
        if ($state) {
            return ImportStepResult::success($state->id());
        }
        if ($sourceActivity && $sourceActivity->idStavu()) {
            return ImportStepResult::successWithErrorLikeWarnings(
                $sourceActivity->idStavu(),
                [sprintf(
                    "Neznámý stav '%s'. Bude použit původní '%s'.",
                    $stateValue,
                    $sourceActivity->stav()->nazev()
                )]
            );
        }
        return ImportStepResult::error(sprintf(
            "Neznámý stav '%s'.",
            $stateValue
        ));
    }

    private function getValidatedEquipment(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $equipmentValue = $activityValues[ExportAktivitSloupce::PRIPRAVA_MISTNOSTI] ?? null;
        if ((string)$equipmentValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity
                ? $sourceActivity->vybaveni()
                : ''
            );
        }
        return ImportStepResult::success($equipmentValue);
    }

    private function getValidatedMinimalTeamCapacity(bool $forTeam, array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        return $this->getValidatedTeamCapacity(
            $forTeam,
            $minimalTeamCapacityValue,
            'minimální',
            $sourceActivity
                ? $sourceActivity->tymMinKapacita()
                : null,
            $originalActivity,
            $parentActivity
        );
    }

    private function getValidatedTeamCapacity(bool $forTeam, ?string $teamCapacityValue, string $capacityName, ?int $capacityOfSourceActivity, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        if ((string)$teamCapacityValue === '') {
            if (!$forTeam && $capacityOfSourceActivity) {
                return ImportStepResult::error(sprintf(
                    'Aktivita není týmová, ale má %s kapacitu %d%s',
                    $capacityName,
                    $capacityOfSourceActivity,
                    $originalActivity === $parentActivity
                        ? ', převzatou z "mateřské" aktivity.'
                        : '.'
                ));
            }
            if ($forTeam && !$capacityOfSourceActivity) {
                return ImportStepResult::error(sprintf('Aktivita je týmová, ale nemá uvedenou %s kapacitu.', $capacityName));
            }
            return ImportStepResult::success($capacityOfSourceActivity ?: 0);
        }
        $teamCapacity = (int)$teamCapacityValue;
        if ($teamCapacity > 0) {
            if (!$forTeam) {
                return ImportStepResult::error(sprintf('Aktivita není týmová, ale má %s kapacitu %d.', $capacityName, $teamCapacity));
            }
            return ImportStepResult::success($teamCapacity);
        }
        if ((string)$teamCapacityValue === '0') {
            if ($forTeam) {
                return ImportStepResult::error(sprintf('Aktivita je týmová, ale má uvedenou %s kapacitu nulovou.', $capacityName));
            }
            return ImportStepResult::success(0);
        }
        return ImportStepResult::error(sprintf(
            "Podivná %s kapacita týmu '%s'. Očekáváme celé kladné číslo.",
            $capacityName,
            $teamCapacityValue
        ));
    }

    private function getValidatedMaximalTeamCapacity(bool $forTeam, array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        return $this->getValidatedTeamCapacity($forTeam, $maximalTeamCapacityValue, 'maximální', $sourceActivity ? $sourceActivity->tymMaxKapacita() : null, $originalActivity, $parentActivity);
    }

    private function getValidatedForTeam(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $forTeamValue = $activityValues[ExportAktivitSloupce::JE_TYMOVA] ?? null;
        if ((string)$forTeamValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success(
                $sourceActivity && $sourceActivity->tymova()
                    ? 1
                    : 0
            );
        }
        $forTeam = $this->parseBoolean($forTeamValue);
        if ($forTeam !== null) {
            return ImportStepResult::success(
                $forTeam
                    ? 1
                    : 0
            );
        }
        return ImportStepResult::error(sprintf(
            "Podivný zápis, zda je aktivita týmová '%s'. Očekáváme pouze 1, 0, ano, ne.",
            $forTeamValue
        ));
    }

    private function getValidatedStorytellersIds(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? '';
        if (!$storytellersString) {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity
                ? $sourceActivity->dejOrganizatoriIds()
                : []
            );
        }
        $storytellersIds = [];
        $unknownUsers = [];
        $notStorytellers = [];
        $storytellersValues = $this->parseArrayFromString($storytellersString);
        foreach ($storytellersValues as $storytellerValue) {
            $user = $this->importObjectsContainer->getUserFromValue($storytellerValue);
            if (!$user) {
                $unknownUsers[] = $storytellerValue;
            } elseif (!$user->maPravoNaPoradaniAktivit()) {
                $notStorytellers[] = $user;
            } else {
                $storytellersIds[] = $user->id();
            }
        }
        $errorLikeWarnings = [];
        if ($unknownUsers) {
            $errorLikeWarnings[] = sprintf(
                'Neznámí uživatelé %s. Jsou vynecháni.',
                implode(',', array_map(static function (string $invalidStorytellerValue) {
                    return "'$invalidStorytellerValue'";
                }, $unknownUsers))
            );
        }
        if ($notStorytellers) {
            $notStorytellersString = implode(',', array_map(function (\Uzivatel $user) {
                return $this->importValuesDescriber->describeUser($user);
            }, $notStorytellers));
            $notStorytellersHtml = htmlentities($notStorytellersString, ENT_QUOTES);
            $errorLikeWarnings[] = <<<HTML
        'Uživatelé {$notStorytellersHtml} nejsou <a href="{$this->storytellersPermissionsUrl}" target="_blank">vypravěči</a>. Nebyli proto k aktivitě jako vypravěči přiřazeni.
HTML;
        }
        return ImportStepResult::successWithErrorLikeWarnings($storytellersIds, $errorLikeWarnings);
    }

    /**
     * @param string $string
     * @return string[]
     */
    private function parseArrayFromString(string $string): array {
        $semicolonOnly = str_replace(',', ';', $string);
        $exploded = explode(';', $semicolonOnly);
        $trimmed = array_map('trim', $exploded);
        return array_filter($trimmed, static function (string $value) {
            return $value !== '';
        });
    }

    private function getValidatedLongAnnotation(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        if (!empty($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE])) {
            return ImportStepResult::success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE]);
        }
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        return ImportStepResult::success($sourceActivity
            ? $sourceActivity->popis()
            : ''
        );
    }

    private function getValidatedTagIds(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        if ($tagsString === '' && $sourceActivity) {
            return $this->getValidatedTagIdsFromActivity($sourceActivity);
        }
        return $this->getValidatedTagsFromString($tagsString);
    }

    private function getValidatedTagsFromString(string $tagsString): ImportStepResult {
        $tagIds = [];
        $invalidTagsValues = [];
        $tagsValues = $this->parseArrayFromString($tagsString);
        foreach ($tagsValues as $tagValue) {
            if ($tagValue === '') {
                continue;
            }
            $tag = $this->importObjectsContainer->getTagFromValue($tagValue);
            if (!$tag) {
                $invalidTagsValues[] = $tagValue;
            } else {
                $tagIds[] = $tag->id();
            }
        }
        if ($invalidTagsValues) {
            return ImportStepResult::error(
                sprintf(
                    'Neznámé tagy %s',
                    implode(',', array_map(static function (string $invalidTagValue) {
                            return "'$invalidTagValue'";
                        },
                            $invalidTagsValues
                        )
                    )
                )
            );
        }
        return ImportStepResult::success($tagIds);
    }

    private function getValidatedTagIdsFromActivity(Aktivita $sourceActivity): ImportStepResult {
        $tagIds = [];
        $invalidTagsValues = [];
        foreach ($sourceActivity->tagy() as $tagValue) {
            $tag = $this->importObjectsContainer->getTagFromValue($tagValue);
            if (!$tag) {
                $invalidTagsValues[] = $tagValue;
            } else {
                $tagIds[] = $tag->id();
            }
        }
        if ($invalidTagsValues) {
            trigger_error(
                sprintf('There are some strange tags coming from activity %s, which are unknown %s', $sourceActivity->id(), implode(',', $invalidTagsValues)),
                E_USER_WARNING
            );
        }
        return ImportStepResult::success($tagIds);
    }

    private function getValidatedShortAnnotation(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        if (!empty($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE])) {
            return ImportStepResult::success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]);
        }
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        return ImportStepResult::success($sourceActivity
            ? $sourceActivity->kratkyPopis()
            : ''
        );
    }

    private function getValidatedInstanceId(?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        return ImportStepResult::success($sourceActivity
            ? $sourceActivity->patriPod()
            : null
        );
    }

    private function getValidatedYear(?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
        if (!$sourceActivity) {
            return ImportStepResult::success($this->currentYear);
        }
        $year = $sourceActivity->zacatek()
            ? (int)$sourceActivity->zacatek()->format('Y')
            : null;
        if (!$year) {
            $year = $sourceActivity->konec()
                ? (int)$sourceActivity->konec()->format('Y')
                : null;
        }
        if ($year && $year !== $this->currentYear) {
            return ImportStepResult::error(sprintf(
                'Aktivita je pro ročník %d, ale teď je ročník %d.',
                $year,
                $this->currentYear
            ));
        }
        return ImportStepResult::success($this->currentYear);
    }

    private function getValidatedWithoutDiscount(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $withoutDiscountValue = $activityValues[ExportAktivitSloupce::BEZ_SLEV] ?? null;
        if ((string)$withoutDiscountValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity && $sourceActivity->bezSlevy()
                ? 1
                : 0
            );
        }
        $withoutDiscount = $this->parseBoolean($withoutDiscountValue);
        if ($withoutDiscount !== null) {
            return ImportStepResult::success(
                $withoutDiscount
                    ? 1
                    : 0
            );
        }
        return ImportStepResult::error(sprintf(
            "Podivný zápis 'bez slevy': '%s'. Očekáváme pouze 1, 0 nebo ano, ne.",
            $withoutDiscountValue
        ));
    }

    private function parseBoolean($value): ?bool {
        if (is_bool($value)) {
            return $value;
        }
        switch (substr((string)$value, 0, 1)) {
            case '0' :
            case 'n' : // ne, no
            case 'f' : // false
                return false;
            case '1' :
            case 'a' : // ano
            case 'j' : // jo
            case 'y' : // yes
            case 't' : // true
                return true;
            default :
                return null;
        }
    }

    private function getValidatedUnisexCapacity(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_UNISEX] ?? null;
        if ((string)$capacityValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity
                ? $sourceActivity->getKapacitaUnisex()
                : null
            );
        }
        $capacityInt = (int)$capacityValue;
        if ($capacityInt > 0) {
            return ImportStepResult::success($capacityInt);
        }
        if ((string)$capacityValue === '0') {
            return ImportStepResult::success(0);
        }
        return ImportStepResult::error(sprintf(
            "Podivná unisex kapacita '%s'. Očekáváme celé kladné číslo.",
            $capacityValue
        ));
    }

    private function getValidatedMenCapacity(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_MUZI] ?? null;
        if ((string)$capacityValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity
                ? $sourceActivity->getKapacitaMuzu()
                : null
            );
        }
        $capacityInt = (int)$capacityValue;
        if ($capacityInt > 0) {
            return ImportStepResult::success($capacityInt);
        }
        if ((string)$capacityValue === '0') {
            return ImportStepResult::success(0);
        }
        return ImportStepResult::error(sprintf(
            "Podivná kapacita mužů '%s'. Očekáváme celé kladné číslo.",
            $capacityValue
        ));
    }

    private function getValidatedWomenCapacity(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_ZENY] ?? null;
        if ((string)$capacityValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return ImportStepResult::success($sourceActivity
                ? $sourceActivity->getKapacitaZen()
                : null
            );
        }
        $capacityInt = (int)$capacityValue;
        if ($capacityInt > 0) {
            return ImportStepResult::success($capacityInt);
        }
        if ((string)$capacityValue === '0') {
            return ImportStepResult::success(0);
        }
        return ImportStepResult::error(sprintf(
            "Podivná kapacita žen '%s'. Očekáváme celé kladné číslo.",
            $capacityValue
        ));
    }

    private function getValidatedChild(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $childrenValue = $activityValues[ExportAktivitSloupce::NASLEDUJICI_SEMIFINALE] ?? null;
        if ((string)$childrenValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            if ($sourceActivity) {
                return ImportStepResult::success($sourceActivity->detiDbString());
            }
            return ImportStepResult::success(null);
        }
        $childrenValues = array_map('trim', explode(',', $childrenValue));
        $childrenIds = [];
        $errorLikeWarnings = [];
        $nextSemifinalLowercase = mb_strtolower(ExportAktivitSloupce::NASLEDUJICI_SEMIFINALE);
        foreach ($childrenValues as $childValue) {
            if ($childValue === '') {
                continue;
            }
            $childId = (int)$childValue;
            if ($childId) {
                if ($originalActivity && $originalActivity->id() === $childId) {
                    $errorLikeWarnings[] = sprintf(
                        "Aktivita '%s' nemůže použít samu sebe jako %s.",
                        $originalActivity->nazev(),
                        $nextSemifinalLowercase
                    );
                    continue;
                }
                if ($parentActivity && $parentActivity->id() === $childId) {
                    $errorLikeWarnings[] = sprintf(
                        "Aktivita '%s' nemůže použít svého rodiče %s jako %s.",
                        $originalActivity->nazev(),
                        $parentActivity->nazev(),
                        $nextSemifinalLowercase
                    );
                    continue;
                }
                $child = Aktivita::zId($childId, true);
                if (!$child) {
                    $errorLikeWarnings[] = sprintf(
                        "Neznámé ID aktivity '%d' (%s). Pro %s nebylo použito.",
                        $childId,
                        $childValue,
                        $nextSemifinalLowercase
                    );
                    continue;
                }
                if ($child->rok() !== $this->currentYear) {
                    $errorLikeWarnings[] = sprintf(
                        "Aktivita %s chtěná jako %s není pro letošní rok, ale byla pro %d. Aktivita nebyla pro %s použita.",
                        $childValue,
                        $nextSemifinalLowercase,
                        $child->rok(),
                        $nextSemifinalLowercase,
                    );
                    continue;
                }
                $childrenIds[] = $childId;
            } else {
                $children = Aktivita::zNazvuARoku($childValue, $this->currentYear);
                if (!$children) {
                    $errorLikeWarnings[] = sprintf(
                        "Neznámá aktivita '%s' pro %s. Pro rok %d nebyla rozpoznána ani podle ID, ani podle názvu.",
                        $childValue,
                        $nextSemifinalLowercase,
                        $this->currentYear
                    );
                }
                foreach ($children as $child) {
                    $childrenIds[] = $child->id();
                }
            }
        }
        $childrenIdsForSql = implode(',', array_unique($childrenIds));
        if ($errorLikeWarnings) {
            return ImportStepResult::successWithErrorLikeWarnings($childrenIdsForSql, $errorLikeWarnings);
        }
        return ImportStepResult::success($childrenIdsForSql);
    }

    private function getValidatedPrice(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
        if ((string)$priceValue === '') {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            if ($sourceActivity) {
                return ImportStepResult::success($sourceActivity->cenaZaklad());
            }
            return ImportStepResult::successWithWarnings(0.0, ['Není uvedena cena. Aktivita bude zadarmo (s cenou 0.-)']);
        }
        $priceFloat = (float)$priceValue;
        if ($priceFloat !== 0.0) {
            return ImportStepResult::success($priceFloat);
        }
        if ((string)$priceFloat === '0' || (string)$priceFloat === '0.0') {
            return ImportStepResult::success(0.0);
        }
        return ImportStepResult::error(sprintf(
            "Podivná cena aktivity '%s'. Očekáváme číslo.",
            $priceValue
        ));
    }

    private function getValidatedLocationId(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $locationValue = $activityValues[ExportAktivitSloupce::MISTNOST] ?? null;
        if (!$locationValue) {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            if ($sourceActivity) {
                return ImportStepResult::success($sourceActivity->lokaceId());
            }
            return ImportStepResult::success(null);
        }
        $location = $this->importObjectsContainer->getLocationFromValue((string)$locationValue);
        if ($location) {
            return ImportStepResult::success($location->id());
        }
        return ImportStepResult::successWithErrorLikeWarnings(
            null,
            [sprintf(
                "Neznámá místnost '%s'. Aktivita je bez místnosti.",
                $locationValue
            )]
        );
    }

    private function getValidatedStart(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $startValue = $activityValues[ExportAktivitSloupce::ZACATEK] ?? null;
        if (!$startValue) {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            if (!$sourceActivity) {
                return ImportStepResult::success(null);
            }
            $activityStart = $sourceActivity->zacatek();
            return ImportStepResult::success($activityStart
                ? $activityStart->formatDb()
                : null
            );
        }
        if (empty($activityValues[ExportAktivitSloupce::DEN])) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    'Aktivita má sice uvedený začátek (%s), ale chybí u ní den. Čas aktivity je vynechán.',
                    $activityValues[ExportAktivitSloupce::ZACATEK]
                )]
            );
        }
        $startDateTime = $this->createDateTimeFromRangeBorder($activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK], 'začátek');
        if ($startDateTime->isSuccess()) {
            $startHour = (int)(DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $startDateTime->getSuccess())->format('G'));
            if (($startHour < PROGRAM_ZACATEK) && $startHour >= PROGRAM_KONEC) {
                return ImportStepResult::successWithErrorLikeWarnings(
                    null,
                    [sprintf(
                        'Aktivita má uvedený začátek (%s), který je mimo rozsah denního začátku a konce GameConu (%s - %s)',
                        $activityValues[ExportAktivitSloupce::ZACATEK],
                        PROGRAM_ZACATEK,
                        PROGRAM_KONEC,
                    )]
                );
            }
        }
        return $startDateTime;
    }

    private function getValidatedEnd(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $activityEndValue = $activityValues[ExportAktivitSloupce::KONEC] ?? null;
        if (!$activityEndValue) {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            if (!$sourceActivity) {
                return ImportStepResult::success(null);
            }
            $activityEnd = $sourceActivity->konec();
            return ImportStepResult::success($activityEnd
                ? $activityEnd->formatDb()
                : null
            );
        }
        if (empty($activityValues[ExportAktivitSloupce::DEN])) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    'Aktivita má sice uvedený konec (%s), ale chybí u ní den. Čas aktivity je vynechán.',
                    $activityValues[ExportAktivitSloupce::KONEC]
                )]
            );
        }

        $endDateTime = $this->createDateTimeFromRangeBorder($activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::KONEC], 'konec');
        if ($endDateTime->isSuccess()) {
            $endHour = (int)(DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $endDateTime->getSuccess())->format('G'));
            if (($endHour < PROGRAM_ZACATEK) && $endHour >= PROGRAM_KONEC) {
                return ImportStepResult::successWithErrorLikeWarnings(
                    null,
                    [sprintf(
                        'Aktivita má uvedený konec (%s), který je mimo rozsah denního začátku a konce GameConu (%s, %s)',
                        $activityValues[ExportAktivitSloupce::ZACATEK],
                        PROGRAM_ZACATEK,
                        PROGRAM_KONEC,
                    )]
                );
            }
        }
        return $endDateTime;
    }

    private function createDateTimeFromRangeBorder(string $dayName, string $hoursAndMinutes, string $timeName): ImportStepResult {
        try {
            $date = DateTimeGamecon::denKolemZacatkuGameconu($dayName, $this->currentYear);
        } catch (\Exception $exception) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    "Nepodařilo se vytvořit datum ze dne '%s' a času '%s'. Chybný formát datumu (%s). %s aktivity je vynechán.",
                    $dayName,
                    $hoursAndMinutes,
                    $exception->getMessage(),
                    mb_ucfirst($timeName)
                )]
            );
        }

        if (!preg_match('~^(?<hours>\d+)(\s*:\s*(?<minutes>\d+))?$~', $hoursAndMinutes, $timeMatches)) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    "Nepodařilo se nastavit čas podle dne '%s' a času '%s'. Chybný formát času '%s'. Čas aktivity je vynechán.",
                    $dayName,
                    $hoursAndMinutes,
                    $hoursAndMinutes
                )]
            );
        }
        $hours = (int)$timeMatches['hours'];
        if ($hours > 24) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    "Nepodařilo se nastavit čas podle dne '%s' a času '%s'. Čas uvádí více než 24 hodin a posunul by datum. Čas aktivity je vynechán.",
                    $dayName,
                    $hoursAndMinutes
                )]
            );
        }
        $minutes = (int)($timeMatches['minutes'] ?? 0);
        if ($minutes >= 60) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    "Nepodařilo se nastavit čas podle dne '%s' a času '%s'. Čas uvádí více než 59 minut a posunul by hodiny. Čas aktivity je vynechán.",
                    $dayName,
                    $hoursAndMinutes
                )]
            );
        }
        $dateTime = $date->setTime($hours, $minutes, 0, 0);
        if ((int)$dateTime->format('G') !== $hours || (int)$dateTime->format('i') !== $minutes) {
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [sprintf(
                    "Nepodařilo se nastavit čas podle dne '%s' a času '%s'. Chybný formát. Čas aktivity je vynechán.",
                    $dayName,
                    $hoursAndMinutes
                )]
            );
        }
        return ImportStepResult::success($dateTime->formatDb());
    }

    private function getValidatedUrl(array $activityValues, ?Aktivita $originalActivity): ImportStepResult {
        $activityUrl = $activityValues[ExportAktivitSloupce::URL] ?? null;
        if (!$activityUrl) {
            if ($originalActivity) {
                return ImportStepResult::success($originalActivity->urlId());
            }
            if (empty($activityValues[ExportAktivitSloupce::NAZEV])) {
                return ImportStepResult::error('Nová aktivita nemá ani URL, ani název, ze kterého by URL šlo vytvořit.');
            }
            $activityUrl = $this->toUrl($activityValues[ExportAktivitSloupce::NAZEV]);
        }
        $activityUrl = $this->toUrl($activityUrl);
        return ImportStepResult::success($activityUrl);
    }

    private function getValidatedParentActivity(string $url, TypAktivity $singleProgramLine): ImportStepResult {
        return ImportStepResult::success(Aktivita::moznaHlavniAktivitaPodleUrl($url, $this->currentYear, $singleProgramLine->id()));
    }

    private function toUrl(string $value): string {
        $sanitized = strtolower(odstranDiakritiku($value));
        return preg_replace('~\W+~', '-', $sanitized);
    }

    private function getValidatedActivityName(array $activityValues, ?Aktivita $originalActivity, ?Aktivita $parentActivity): ImportStepResult {
        $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
        if (!$activityNameValue) {
            $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
            return $sourceActivity
                ? ImportStepResult::success($sourceActivity->nazev())
                : ImportStepResult::error('Chybí povinný název.');
        }
        return ImportStepResult::success($activityNameValue);
    }

    private function getSourceActivity(?Aktivita $originalActivity, ?Aktivita $parentActivity): ?Aktivita {
        return $originalActivity ?: $parentActivity;
    }

    private function getValidatedProgramLineId(array $activityValues, TypAktivity $singleProgramLine): ImportStepResult {
        $programLineValue = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
        if ((string)$programLineValue === '') {
            return ImportStepResult::success($singleProgramLine->id());
        }
        $programLine = $this->importObjectsContainer->getProgramLineFromValue((string)$programLineValue);
        if (!$programLine) {
            return ImportStepResult::error(sprintf(
                "Neznámá programová linie '%s'.",
                $programLineValue
            ));
        }
        if ($programLine->id() !== $singleProgramLine->id()) {
            return ImportStepResult::error(sprintf(
                "Importovat lze pouze jednu programovou linii. Tato aktivita patří do další linie '%s'.",
                $programLineValue
            ));
        }
        return ImportStepResult::success($programLine->id());
    }
}
