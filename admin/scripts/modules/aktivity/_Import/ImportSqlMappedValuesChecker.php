<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Role\Role;

class ImportSqlMappedValuesChecker
{
    public function __construct(
        private readonly int                   $currentYear,
        private readonly \DateTimeInterface    $now,
        private readonly ImportValuesDescriber $importValuesDescriber,
    )
    {
    }

    public function checkBeforeSave(
        array       $sqlMappedValues,
        ?string     $longAnnotation,
        array       $tagIds,
        array       $storytellersIds,
        TypAktivity $singleProgramLine,
        array       $potentialImageUrls,
        ?Aktivita   $originalActivity,
    ): ImportStepResult
    {
        $checkResults = [];

        $timeResult = $this->checkTime($sqlMappedValues, $originalActivity);
        if ($timeResult->isError()) {
            return ImportStepResult::error($timeResult->getError());
        }
        ['start' => $start, 'end' => $end] = $timeResult->getSuccess();
        $sqlMappedValues[ActivitiesImportSqlColumn::ZACATEK] = $start ?: null;
        $sqlMappedValues[ActivitiesImportSqlColumn::KONEC]   = $end ?: null;
        $checkResults[]                                      = $timeResult;
        unset($timeResult);

        $urlUniquenessResult = $this->checkUrlUniqueness($sqlMappedValues, $singleProgramLine, $originalActivity);
        if ($urlUniquenessResult->isError()) {
            return ImportStepResult::error($urlUniquenessResult->getError());
        }
        $checkResults[] = $urlUniquenessResult;
        unset($urlUniquenessResult);

        $nameUniqueness = $this->checkNameUniqueness($sqlMappedValues, $singleProgramLine, $originalActivity);
        if ($nameUniqueness->isError()) {
            return ImportStepResult::error($nameUniqueness->getError());
        }
        $checkResults[] = $nameUniqueness;
        unset($nameUniqueness);

        $stateUsabilityResult = $this->checkStateUsability($sqlMappedValues, $originalActivity);
        if ($stateUsabilityResult->isError()) {
            return ImportStepResult::error($stateUsabilityResult->getError());
        }
        $sqlMappedValues[ActivitiesImportSqlColumn::STAV] = $stateUsabilityResult->getSuccess();
        $checkResults[]                                   = $stateUsabilityResult;
        unset($stateUsabilityResult);

        $requiredValuesForStateResult = $this->checkRequiredValuesForState($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);
        if ($requiredValuesForStateResult->isError()) {
            return ImportStepResult::error($requiredValuesForStateResult->getError());
        }
        $sqlMappedValues[ActivitiesImportSqlColumn::STAV] = $requiredValuesForStateResult->getSuccess();
        $checkResults[]                                   = $requiredValuesForStateResult;
        unset($requiredValuesForStateResult);

        $storytellersAccessibilityResult = $this->checkStorytellersAccessibility(
            $storytellersIds,
            $sqlMappedValues[ActivitiesImportSqlColumn::ZACATEK],
            $sqlMappedValues[ActivitiesImportSqlColumn::KONEC],
            $originalActivity,
        );
        if ($storytellersAccessibilityResult->isError()) {
            return ImportStepResult::error($storytellersAccessibilityResult->getError());
        }
        $availableStorytellerIds = $storytellersAccessibilityResult->getSuccess();
        $checkResults[]          = $storytellersAccessibilityResult;
        unset($storytellersAccessibilityResult);

        $locationAccessibilityResult = self::checkLocationByAccessibility(
            $sqlMappedValues[ActivitiesImportSqlColumn::LOKACE],
            $sqlMappedValues[ActivitiesImportSqlColumn::ZACATEK],
            $sqlMappedValues[ActivitiesImportSqlColumn::KONEC],
            $originalActivity,
            $singleProgramLine,
            $this->importValuesDescriber,
        );
        if ($locationAccessibilityResult->isError()) {
            return ImportStepResult::error($locationAccessibilityResult->getError());
        }
        $checkResults[] = $locationAccessibilityResult;
        unset($locationAccessibilityResult);

        $teamCapacityRangeResult = $this->checkTeamCapacityRange(
            (bool)$sqlMappedValues[ActivitiesImportSqlColumn::TEAMOVA],
            $sqlMappedValues[ActivitiesImportSqlColumn::TEAM_MIN],
            $sqlMappedValues[ActivitiesImportSqlColumn::TEAM_MAX],
        );
        if ($teamCapacityRangeResult->isError()) {
            return ImportStepResult::error($teamCapacityRangeResult->getError());
        }
        $checkResults[] = $teamCapacityRangeResult;
        unset($teamCapacityRangeResult);

        $nonTeamCapacityResult = $this->checkNonTeamCapacity(
            (bool)$sqlMappedValues[ActivitiesImportSqlColumn::TEAMOVA],
            TypAktivity::jeInterniDleId($sqlMappedValues[ActivitiesImportSqlColumn::TYP]),
            $sqlMappedValues[ActivitiesImportSqlColumn::KAPACITA],
            $sqlMappedValues[ActivitiesImportSqlColumn::KAPACITA_M],
            $sqlMappedValues[ActivitiesImportSqlColumn::KAPACITA_F],
        );
        if ($nonTeamCapacityResult->isError()) {
            return ImportStepResult::error($nonTeamCapacityResult->getError());
        }
        $checkResults[] = $nonTeamCapacityResult;
        unset($nonTeamCapacityResult);

        return ImportStepResult::success([
            'values'                  => $sqlMappedValues,
            'availableStorytellerIds' => $availableStorytellerIds,
            'checkResults'            => $checkResults,
        ]);
    }

    private function checkTime(array $sqlMappedValues, ?Aktivita $originalActivity): ImportStepResult
    {
        if ($originalActivity) {
            if ($originalActivity->zacatek() && $originalActivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
                return ImportStepResult::error(sprintf(
                    'Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s).',
                    $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->zacatek()->formatCasNaMinutyStandard(),
                ));
            }
            if ($originalActivity->konec() && $originalActivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
                return ImportStepResult::error(sprintf(
                    'Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s).',
                    $this->importValuesDescriber->describeActivity($originalActivity),
                    $originalActivity->konec()->formatCasNaMinutyStandard(),
                ));
            }
        }

        $startString = $sqlMappedValues[ActivitiesImportSqlColumn::ZACATEK];
        $endString   = $sqlMappedValues[ActivitiesImportSqlColumn::KONEC];
        if (!$startString && !$endString) {
            return ImportStepResult::success(['start' => null, 'end' => null]);
        }
        if (!$startString || !$endString) {
            return ImportStepResult::successWithErrorLikeWarnings(
                ['start' => null, 'end' => null],
                [
                    sprintf(
                        "Není vyplněný %s, pouze %s '%s'. Čas aktivity je vynechán.",
                        !$startString
                            ? 'začátek'
                            : 'konec',
                        $startString
                            ? 'začátek'
                            : 'konec',
                        $startString
                            ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $startString)->formatCasNaMinutyStandard()
                            : DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $endString)->formatCasNaMinutyStandard(),
                    ),
                ],
            );
        }
        $start = DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $startString);
        $end   = DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $endString);
        if ($start->getTimestamp() > $end->getTimestamp()) {
            if ($originalActivity && $originalActivity->zacatek() && $originalActivity->konec()) {
                return ImportStepResult::successWithErrorLikeWarnings(
                    ['start' => $originalActivity->zacatek()->formatDb(), 'end' => $originalActivity->konec()->formatDb()],
                    [
                        sprintf(
                            "Začátek '%s' je až po konci '%s'. Ponechán původní čas od '%s' do '%s'.",
                            $start->formatCasNaMinutyStandard(),
                            $end->formatCasNaMinutyStandard(),
                            $originalActivity->zacatek()->formatCasNaMinutyStandard(),
                            $originalActivity->konec()->formatCasNaMinutyStandard(),
                        ),
                    ],
                );
            }
            return ImportStepResult::successWithErrorLikeWarnings(
                ['start' => null, 'end' => null],
                [
                    sprintf(
                        "Začátek '%s' je až po konci '%s'. Čas aktivity je vynechán.",
                        $start->formatCasNaMinutyStandard(),
                        $end->formatCasNaMinutyStandard(),
                    ),
                ],
            );
        }
        if ($end->getTimestamp() === $start->getTimestamp()) {
            return ImportStepResult::successWithErrorLikeWarnings(
                ['start' => null, 'end' => null],
                [
                    sprintf(
                        "Konec je stejný jako začátek '%s'. Aktivita by měla mít nějaké trvání. Čas aktivity je vynechán.",
                        $end->formatCasNaMinutyStandard(),
                    ),
                ],
            );
        }
        return ImportStepResult::success(['start' => $startString, 'end' => $endString]);
    }

    private function checkUrlUniqueness(array $sqlMappedValues, TypAktivity $singleProgramLine, ?Aktivita $originalActivity): ImportStepResult
    {
        $activityUrl          = $sqlMappedValues[ActivitiesImportSqlColumn::URL_AKCE];
        $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, patri_pod
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
            ,
            [$activityUrl, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0],
        );
        if (!$occupiedByActivities) {
            return ImportStepResult::success(null);
        }
        foreach ($occupiedByActivities as $occupiedByActivity) {
            if (!$this->canShareNameOrUrlWith($activityUrl, $singleProgramLine, $occupiedByActivity, $originalActivity)) {
                return ImportStepResult::error(sprintf(
                    "URL '%s'%s už je obsazena jinou existující aktivitou %s.",
                    $activityUrl,
                    $activityUrl === ''
                        ? ' (odhadnutá z názvu)'
                        : '',
                    $this->importValuesDescriber->describeActivityById((int)$occupiedByActivity['id_akce']),
                ));
            }
        }
        return ImportStepResult::success(null);
    }

    private function canShareNameOrUrlWith(
        string      $activityUrl,
        TypAktivity $singleProgramLine,
        array       $urlOccupiedByActivity,
        ?Aktivita   $originalActivity,
    ): bool
    {
        $occupiedByInstanceFamilyId = $urlOccupiedByActivity['patri_pod']
            ? (int)$urlOccupiedByActivity['patri_pod']
            : null;
        if ($occupiedByInstanceFamilyId) {
            return $this->isSameInstanceFamily($activityUrl, $singleProgramLine, $occupiedByInstanceFamilyId, $originalActivity);
        }
        $occupiedByActivityId = (int)$urlOccupiedByActivity['id_akce'];
        return $this->willBeNewInstanceOfActivity($activityUrl, $singleProgramLine, $occupiedByActivityId);
    }

    private function willBeNewInstanceOfActivity(string $url, TypAktivity $singleProgramLine, int $parentActivityId): bool
    {
        $possibleParentActivityId = Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
        return $possibleParentActivityId === $parentActivityId;
    }

    private function isSameInstanceFamily(
        string      $activityUrl,
        TypAktivity $singleProgramLine,
        int         $occupiedByInstanceFamilyId,
        ?Aktivita   $originalActivity,
    ): bool
    {
        $instanceFamilyId = $originalActivity
            ? $originalActivity->patriPod()
            : $this->getInstanceFamilyIdByUrl($activityUrl, $singleProgramLine->id());
        return $instanceFamilyId === $occupiedByInstanceFamilyId;
    }

    private function getInstanceFamilyIdByUrl(string $url, int $programLineId): ?int
    {
        return Aktivita::idExistujiciInstancePodleUrl($url, $this->currentYear, $programLineId);
    }

    private function checkNameUniqueness(array $sqlMappedValues, TypAktivity $singleProgramLine, ?Aktivita $originalActivity): ImportStepResult
    {
        $activityName             = $sqlMappedValues[ActivitiesImportSqlColumn::NAZEV_AKCE];
        $nameOccupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
            , [$activityName, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0],
        );
        if (!$nameOccupiedByActivities) {
            return ImportStepResult::success(null);
        }
        $activityUrl = $sqlMappedValues[ActivitiesImportSqlColumn::URL_AKCE];
        foreach ($nameOccupiedByActivities as $occupiedByActivity) {
            if (!$this->canShareNameOrUrlWith($activityUrl, $singleProgramLine, $occupiedByActivity, $originalActivity)) {
                return ImportStepResult::error(sprintf(
                    "Název '%s' už je obsazený jinou existující aktivitou %s.",
                    $activityName,
                    $this->importValuesDescriber->describeActivityById((int)$occupiedByActivity['id_akce']),
                ));
            }
        }
        return ImportStepResult::success(null);
    }

    private function checkStateUsability(array $sqlMappedValues, ?Aktivita $originalActivity): ImportStepResult
    {
        if ($originalActivity && !$originalActivity->bezpecneEditovatelna()) {
            return ImportStepResult::error(sprintf(
                "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'.",
                $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->stav()->nazev(),
            ));
        }
        $stateId = $sqlMappedValues[ActivitiesImportSqlColumn::STAV];
        if ($stateId === null) {
            return ImportStepResult::success(null);
        }
        $state = StavAktivity::zId($stateId);
        if ($state->jeNanejvysPripravenaKAktivaci()) {
            return ImportStepResult::success($state->id());
        }
        return ImportStepResult::successWithErrorLikeWarnings(
            StavAktivity::PRIPRAVENA,
            [
                sprintf(
                    "Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl změněn na '%s'.",
                    $state->nazev(),
                    StavAktivity::zId(StavAktivity::PRIPRAVENA)->nazev(),
                ),
            ],
        );
    }

    private function checkRequiredValuesForState(array $sqlMappedValues, ?string $longAnnotation, array $tagIds, array $potentialImageUrls): ImportStepResult
    {
        $stateId = $sqlMappedValues[ActivitiesImportSqlColumn::STAV];
        if ($stateId === null) {
            return ImportStepResult::success(null);
        }
        $state = StavAktivity::zId($stateId);
        if ($state->jePublikovana()) {
            $requiredFieldsForPublishingResult = $this->checkRequiredFieldsForPublishing($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);
            if ($requiredFieldsForPublishingResult->isError()) {
                return $requiredFieldsForPublishingResult;
            }
        } else if ($state->jePripravenaKAktivaci()) {
            $requiredFieldsForReadyForActivationResult = $this->checkRequiredFieldsForReadyToActivation($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);
            if ($requiredFieldsForReadyForActivationResult->isError()) {
                return $requiredFieldsForReadyForActivationResult;
            }
        }
        if ($state->jeNanejvysPripravenaKAktivaci()) {
            return ImportStepResult::success($state->id());
        }
        return ImportStepResult::successWithErrorLikeWarnings(
            StavAktivity::PRIPRAVENA,
            [
                sprintf(
                    "Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl změněn na '%s'.",
                    $state->nazev(),
                    StavAktivity::zId(StavAktivity::PRIPRAVENA)->nazev(),
                ),
            ],
        );
    }

    // for "připravená"
    private function checkRequiredFieldsForReadyToActivation(
        array   $sqlMappedValues,
        ?string $longAnnotation,
        array   $tagIds,
        array   $potentialImageUrls,
    ): ImportStepResult
    {
        $sqlMappedValues = $this->extendValuesByVirtualColumns($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);

        $requiredNonEmptyFields = [
            ActivitiesImportSqlColumn::NAZEV_AKCE,
            ActivitiesImportSqlColumn::URL_AKCE,
            ActivitiesImportSqlColumn::ZACATEK,
            ActivitiesImportSqlColumn::KONEC,
            ActivitiesImportSqlColumn::LOKACE,
            ActivitiesImportSqlColumn::POPIS_KRATKY,
            ActivitiesImportSqlColumn::POPIS,
            ActivitiesImportSqlColumn::VYBAVENI,
            ActivitiesImportSqlColumn::VIRTUAL_IMAGE,
            ActivitiesImportSqlColumn::VIRTUAL_TAGS,
        ];

        $requiredFieldsAcceptingZero = [
            ActivitiesImportSqlColumn::CENA,
            ActivitiesImportSqlColumn::BEZ_SLEVY,
            ActivitiesImportSqlColumn::NEDAVA_SLEVU,
            ActivitiesImportSqlColumn::KAPACITA,
            ActivitiesImportSqlColumn::KAPACITA_F,
            ActivitiesImportSqlColumn::KAPACITA_M,
        ];
        if ($sqlMappedValues[ActivitiesImportSqlColumn::TEAMOVA]) {
            $requiredFieldsAcceptingZero[] = ActivitiesImportSqlColumn::TEAM_MIN;
            $requiredFieldsAcceptingZero[] = ActivitiesImportSqlColumn::TEAM_MAX;
        }
        $missingRequiredFields = $this->getMissingRequiredFieldsForState($sqlMappedValues, $requiredNonEmptyFields, $requiredFieldsAcceptingZero);

        if ($missingRequiredFields) {
            return ImportStepResult::error(sprintf(
                'Pro připravení k aktivaci musíš aktivitě vyplnit ještě %s.',
                implode(', ', $missingRequiredFields),
            ));
        }
        return ImportStepResult::success(null);
    }

    private function extendValuesByVirtualColumns(array $sqlMappedValues, ?string $longAnnotation, array $tagIds, array $potentialImageUrls): array
    {
        $sqlMappedValues[ActivitiesImportSqlColumn::VIRTUAL_IMAGE] = implode(',', array_filter($potentialImageUrls));
        $sqlMappedValues[ActivitiesImportSqlColumn::VIRTUAL_TAGS]  = implode(',', array_filter($tagIds));
        $sqlMappedValues[ActivitiesImportSqlColumn::POPIS]         = $longAnnotation; // popis is a texts.id in fact, but we will use it as final text content here
        return $sqlMappedValues;
    }

    private function checkRequiredFieldsForPublishing(array $sqlMappedValues, ?string $longAnnotation, array $tagIds, array $potentialImageUrls): ImportStepResult
    {
        $sqlMappedValues = $this->extendValuesByVirtualColumns($sqlMappedValues, $longAnnotation, $tagIds, $potentialImageUrls);

        $requiredNonEmptyFields = [
            ActivitiesImportSqlColumn::NAZEV_AKCE,
            ActivitiesImportSqlColumn::URL_AKCE,
            ActivitiesImportSqlColumn::POPIS_KRATKY,
            ActivitiesImportSqlColumn::POPIS,
            ActivitiesImportSqlColumn::VIRTUAL_IMAGE,
            ActivitiesImportSqlColumn::VIRTUAL_TAGS,
        ];

        $missingNames = $this->getMissingRequiredFieldsForState($sqlMappedValues, $requiredNonEmptyFields, []);
        if ($missingNames) {
            return ImportStepResult::error(sprintf(
                'Pro publikování musíš aktivitě vyplnit ještě %s.',
                implode(', ', $missingNames),
            ));
        }
        return ImportStepResult::success(null);
    }

    private function getMissingRequiredFieldsForState(array $sqlMappedValues, array $requiredNonEmptyFields, array $requiredFieldsAcceptingZero): array
    {
        $missingFields = [];
        foreach ($requiredNonEmptyFields as $requiredNonEmptyField) {
            if (empty($sqlMappedValues[$requiredNonEmptyField])) {
                $missingFields[] = $requiredNonEmptyField;
            }
        }
        foreach ($requiredFieldsAcceptingZero as $requiredFieldAcceptingZero) {
            if (!isset($sqlMappedValues[$requiredFieldAcceptingZero]) || (string)$sqlMappedValues[$requiredFieldAcceptingZero] === '') {
                $missingFields[] = $requiredFieldAcceptingZero;
            }
        }
        if (!$missingFields) {
            return [];
        }
        $missingFieldsAsKeys = array_fill_keys($missingFields, true);
        return array_intersect_key(self::getFieldsToNames(), $missingFieldsAsKeys);
    }

    private static function getFieldsToNames(): array
    {
        return [
            ActivitiesImportSqlColumn::NAZEV_AKCE    => ExportAktivitSloupce::NAZEV,
            ActivitiesImportSqlColumn::URL_AKCE      => ExportAktivitSloupce::URL,
            ActivitiesImportSqlColumn::POPIS_KRATKY  => ExportAktivitSloupce::KRATKA_ANOTACE,
            ActivitiesImportSqlColumn::POPIS         => ExportAktivitSloupce::DLOUHA_ANOTACE,
            ActivitiesImportSqlColumn::VIRTUAL_TAGS  => ExportAktivitSloupce::TAGY,
            ActivitiesImportSqlColumn::VIRTUAL_IMAGE => ExportAktivitSloupce::OBRAZEK,
        ];
    }

    public static function checkLocationByAccessibility(
        ?int                  $idLokace,
        ?string               $zacatekString,
        ?string               $konecString,
        ?Aktivita             $puvodniAktivita,
        TypAktivity           $soucasnyTypAktivity,
        ImportValuesDescriber $importValuesDescriber,
    ): ImportStepResult
    {
        if ($idLokace === null) {
            return ImportStepResult::success(null);
        }
        $rangeDates = self::createRangeDates($zacatekString, $konecString);
        if (!$rangeDates) {
            return ImportStepResult::success(true);
        }
        /** @var DateTimeCz $zacatek */
        /** @var DateTimeCz $konec */
        ['start' => $zacatek, 'end' => $konec] = $rangeDates;
        $locationOccupyingActivityIds = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE akce_seznam.lokace = $0
AND akce_seznam.zacatek <= $1 -- jina zacala na konci nebo pred koncem nove
AND akce_seznam.konec >= $2 -- jina skoncila na zacatku nebo po zacatku nove
AND akce_seznam.typ NOT IN ($3) -- jen aktivity kterym vadi, ze by sdilely mistnost
AND IF ($4 IS NULL, TRUE, akce_seznam.typ != $4) -- jen ostatni typy aktivit, pokud soucasne nevadi stejny typ
AND IF ($5 IS NULL, TRUE, akce_seznam.id_akce != $5) -- jen jine aktivity
SQL,
            [
                0 => $idLokace,
                1 => $konec->formatDb(),
                2 => $zacatek->formatDb(),
                3 => $soucasnyTypAktivity::typyKterymNevadiSdileniMistnostiSZadnymiTypy(),
                4 => $soucasnyTypAktivity->nevadiMuSdileniMistnostiSeStejnymTypem()
                    ? $soucasnyTypAktivity->id()
                    : null,
                5 => $puvodniAktivita?->id(),
            ],
        );
        if (count($locationOccupyingActivityIds) === 0) {
            return ImportStepResult::success($idLokace);
        }
        $activitiesDescription = count($locationOccupyingActivityIds) > 1
            ? 'jinými aktivitami'
            : 'jinou aktivitou';
        $activitiesDescription .= ' ' . implode(
                ' a ',
                array_map(
                    static function ($locationOccupyingActivityIds) use ($importValuesDescriber) {
                        return $importValuesDescriber->describeActivityById((int)$locationOccupyingActivityIds);
                    },
                    $locationOccupyingActivityIds,
                ),
            );
        $activitiesDescription .= $soucasnyTypAktivity->sdileniMistnostiJeProNiProblem()
            ? ''
            : " jiného typu než '{$soucasnyTypAktivity->nazev()}'";
        return ImportStepResult::successWithWarnings(
            $idLokace,
            [
                sprintf(
                    'Varování: Místnost %s je někdy mezi %s a %s již zabraná %s. Teď do ní byla přidána %d. aktivita.',
                    $importValuesDescriber->describeLocationById($idLokace),
                    $zacatek->formatCasNaMinutyStandard(),
                    $konec->formatCasNaMinutyStandard(),
                    $activitiesDescription,
                    count($locationOccupyingActivityIds) + 1,
                ),
            ],
        );
    }

    private function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?Aktivita $originalActivity): ImportStepResult
    {
        $rangeDates = self::createRangeDates($zacatekString, $konecString);
        if (!$rangeDates) {
            return ImportStepResult::success($storytellersIds);
        }
        /** @var DateTimeCz $zacatek */
        /** @var DateTimeCz $konec */
        ['start' => $zacatek, 'end' => $konec] = $rangeDates;
        $occupiedStorytellers    = dbArrayCol(<<<SQL
SELECT id_uzivatele, activity_ids
FROM (
    SELECT akce_organizatori.id_uzivatele,
           GROUP_CONCAT(DISTINCT akce_organizatori.id_akce SEPARATOR ',') AS activity_ids,
           FIND_IN_SET($4, GROUP_CONCAT(DISTINCT platne_role_uzivatelu.id_role SEPARATOR ',')) AS user_is_group_in_fact
    FROM akce_organizatori
    JOIN akce_seznam ON akce_organizatori.id_akce = akce_seznam.id_akce
    LEFT JOIN platne_role_uzivatelu ON akce_organizatori.id_uzivatele = platne_role_uzivatelu.id_uzivatele
    WHERE
        /* povolit navazování aktivit přímo na sebe pro téhož vypravěče
           https://trello.com/c/bGIZcH9N/792-hromadn%C3%A9-vkl%C3%A1d%C3%A1n%C3%AD-do-adminu-v11 */
        $1 < akce_seznam.konec /* importovaná aktivita začíná před koncem nějaké už existující */
        AND $2 > akce_seznam.zacatek /* importovaná aktivita končí po začátku té už existující */
        AND IF($3 IS NULL, TRUE, akce_seznam.id_akce != $3)
    GROUP BY akce_organizatori.id_uzivatele
) AS with_groups_as_users
/* umožnit kolizi aktivit vedených vypravěčskou skupinou
   https://trello.com/c/bGIZcH9N/792-hromadn%C3%A9-vkl%C3%A1d%C3%A1n%C3%AD-do-adminu-v11 */
WHERE NOT user_is_group_in_fact
SQL
            , [
                $zacatek->formatDb(),
                $konec->formatDb(),
                $originalActivity ? $originalActivity->id() : null,
                Role::VYPRAVECSKA_SKUPINA,
                $originalActivity ? $originalActivity->dejOrganizatoriIds() : null,
            ],
        );
        $conflictingStorytellers = array_intersect_key($occupiedStorytellers, array_fill_keys($storytellersIds, true));
        if (!$conflictingStorytellers) {
            return ImportStepResult::success($storytellersIds);
        }
        $errorLikeWarnings = [];
        foreach ($conflictingStorytellers as $conflictingStorytellerId => $implodedAnotherActivityIds) {
            $anotherActivityIds  = explode(',', $implodedAnotherActivityIds);
            $errorLikeWarnings[] = sprintf(
                'Vypravěč %s je někdy v čase od %s do %s na jiné aktivitě %s. K současné aktivitě nebyl přiřazen.',
                $this->importValuesDescriber->describeUserById((int)$conflictingStorytellerId),
                $zacatek->formatCasStandard(),
                $konec->formatCasStandard(),
                implode(' a ', array_map(function ($anotherActivityId) {
                    return $this->importValuesDescriber->describeActivityById((int)$anotherActivityId);
                }, $anotherActivityIds)),
            );
        }
        return ImportStepResult::successWithErrorLikeWarnings(
            array_diff($storytellersIds, array_keys($occupiedStorytellers)),
            $errorLikeWarnings,
        );
    }

    /**
     * @return null|array<string, DateTimeCz>
     */
    private static function createRangeDates(?string $zacatekString, ?string $konecString): ?array
    {
        if ($zacatekString === null && $konecString === null) {
            // nothing to check, we do not know the activity time
            return null;
        }
        $zacatek = $zacatekString
            ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $zacatekString)
            : null;
        $konec   = $konecString
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

    private function checkTeamCapacityRange(
        bool $isTeamActivity,
        ?int $minimalTeamCapacity,
        ?int $maximalTeamCapacity,
    ): ImportStepResult
    {
        if (!$isTeamActivity) {
            return ImportStepResult::success(null);
        }
        if ((int)$minimalTeamCapacity > (int)$maximalTeamCapacity) {
            return ImportStepResult::error(sprintf(
                'Minimální týmová kapacita %d nemůže být větší než maximální %d.',
                $minimalTeamCapacity,
                $maximalTeamCapacity,
            ));
        }
        return ImportStepResult::success(null);
    }

    private function checkNonTeamCapacity(
        bool $isTeamActivity,
        bool $isInternalActivity,
        ?int $unisexCapacity,
        ?int $menCapacity,
        ?int $womenCapacity,
    ): ImportStepResult
    {
        if ($isTeamActivity || $isInternalActivity) {
            return ImportStepResult::success(null);
        }
        if (($unisexCapacity ?: 0) + ($menCapacity ?: 0) + ($womenCapacity ?: 0) === 0) {
            return ImportStepResult::successWithWarnings(
                null,
                [
                    'Kapacita aktivity by neměla být nulová. Alespoň jedna z kapacit unisex, mužská nebo ženská by měly být vyplněné.',
                ],
            );
        }
        return ImportStepResult::success(null);
    }
}
