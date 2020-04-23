<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Cas\DateTimeCz;

class ImportSqlMappedValuesChecker
{
  /**
   * @var ImportValuesDescriber
   */
  private $importValuesDescriber;
  /**
   * @var \DateTimeInterface
   */
  private $now;
  /**
   * @var int
   */
  private $currentYear;

  public function __construct(
    int $currentYear,
    \DateTimeInterface $now,
    ImportValuesDescriber $importValuesDescriber
  ) {
    $this->importValuesDescriber = $importValuesDescriber;
    $this->now = $now;
    $this->currentYear = $currentYear;
  }

  public function checkTime(array $sqlMappedValues, ?\Aktivita $originalActivity): ImportStepResult {
    if ($originalActivity) {
      if ($originalActivity->zacatek() && $originalActivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          'Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s).',
          $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->zacatek()->formatCasNaMinutyStandard()
        ));
      }
      if ($originalActivity->konec() && $originalActivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
        return ImportStepResult::error(sprintf(
          'Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s).',
          $this->importValuesDescriber->describeActivity($originalActivity),
          $originalActivity->konec()->formatCasNaMinutyStandard()
        ));
      }
    }

    $startString = $sqlMappedValues[AktivitaSqlSloupce::ZACATEK];
    $endString = $sqlMappedValues[AktivitaSqlSloupce::KONEC];
    if (!$startString && !$endString) {
      return ImportStepResult::success(['start' => null, 'end' => null]);
    }
    if (!$startString || !$endString) {
      return ImportStepResult::successWithErrorLikeWarnings(
        ['start' => null, 'end' => null],
        [sprintf(
          "Není vyplněný %s, pouze %s '%s'. Čas aktivity je vynechán.",
          !$startString
            ? 'začátek'
            : 'konec',
          $startString
            ? 'začátek'
            : 'konec',
          $startString
            ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $startString)->formatCasNaMinutyStandard()
            : DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $endString)->formatCasNaMinutyStandard()
        )]
      );
    }
    $start = DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $startString);
    $end = DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $endString);
    if ($start->getTimestamp() > $end->getTimestamp()) {
      if ($originalActivity && $originalActivity->zacatek() && $originalActivity->konec()) {
        return ImportStepResult::successWithErrorLikeWarnings(
          ['start' => $originalActivity->zacatek()->formatDb(), 'end' => $originalActivity->konec()->formatDb()],
          [sprintf(
            "Začátek '%s' je až po konci '%s'. Ponechán původní čas od '%s' do '%s'.",
            $start->formatCasNaMinutyStandard(),
            $end->formatCasNaMinutyStandard(),
            $originalActivity->zacatek()->formatCasNaMinutyStandard(),
            $originalActivity->konec()->formatCasNaMinutyStandard()
          )]
        );
      }
      return ImportStepResult::successWithErrorLikeWarnings(
        ['start' => null, 'end' => null],
        [sprintf(
          "Začátek '%s' je až po konci '%s'. Čas aktivity je vynechán.",
          $start->formatCasNaMinutyStandard(),
          $end->formatCasNaMinutyStandard()
        )]
      );
    }
    if ($end->getTimestamp() === $start->getTimestamp()) {
      return ImportStepResult::successWithErrorLikeWarnings(
        ['start' => null, 'end' => null],
        [sprintf(
          "Konec je stejný jako začátek '%s'. Aktivita by měla mít nějaké trvání. Čas aktivity je vynechán.",
          $end->formatCasNaMinutyStandard()
        )]
      );
    }
    return ImportStepResult::success(['start' => $startString, 'end' => $endString]);
  }

  public function checkUrlUniqueness(array $sqlMappedValues, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ImportStepResult {
    $activityUrl = $sqlMappedValues[AktivitaSqlSloupce::URL_AKCE];
    $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, patri_pod
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
      ,
      [$activityUrl, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0]
    );
    if (!$occupiedByActivities) {
      return ImportStepResult::success(null);
    }
    foreach ($occupiedByActivities as $occupiedByActivity) {
      $occupiedByActivityId = (int)$occupiedByActivity['id_akce'];
      $occupiedByInstanceId = $occupiedByActivity['patri_pod']
        ? (int)$occupiedByActivity['patri_pod']
        : null;
      if (($occupiedByInstanceId && $this->isDifferentInstance($activityUrl, $singleProgramLine, $occupiedByInstanceId, $originalActivity))
        || (!$occupiedByInstanceId && $this->canNotBeNewInstanceOfActivity($activityUrl, $singleProgramLine, $occupiedByActivityId))
      ) {
        return ImportStepResult::error(sprintf(
          "URL '%s'%s už je obsazena jinou existující aktivitou %s.",
          $activityUrl,
          empty($activityValues[ExportAktivitSloupce::URL])
            ? ' (odhadnutá z názvu)'
            : '',
          $this->importValuesDescriber->describeActivityById($occupiedByActivityId)
        ));
      }
    }
    return ImportStepResult::success(null);
  }

  private function canNotBeNewInstanceOfActivity(string $url, \Typ $singleProgramLine, int $parentActivityId): bool {
    $possibleParentActivityId = \Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
    return $possibleParentActivityId !== $parentActivityId;
  }

  private function isDifferentInstance(
    string $activityUrl,
    \Typ $singleProgramLine,
    int $occupiedByInstanceId,
    ?\Aktivita $originalActivity
  ): bool {
    $instanceId = $originalActivity
      ? $originalActivity->patriPod()
      : $this->getInstanceIdByUrl($activityUrl, $singleProgramLine->id());
    return $instanceId && $instanceId !== $occupiedByInstanceId;
  }

  private function getInstanceIdByUrl(string $url, int $programLineId): ?int {
    return \Aktivita::idExistujiciInstancePodleUrl($url, $this->currentYear, $programLineId);
  }

  public function checkNameUniqueness(array $sqlMappedValues, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ImportStepResult {
    $activityUrl = $sqlMappedValues[AktivitaSqlSloupce::URL_AKCE];
    $activityName = $sqlMappedValues[AktivitaSqlSloupce::NAZEV_AKCE];
    $nameOccupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
      , [$activityName, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0]
    );
    if (!$nameOccupiedByActivities) {
      return ImportStepResult::success(null);
    }
    foreach ($nameOccupiedByActivities as $occupiedByActivity) {
      $occupiedByActivityId = (int)$occupiedByActivity['id_akce'];
      $occupiedByInstanceId = $occupiedByActivity['patri_pod']
        ? (int)$occupiedByActivity['patri_pod']
        : null;
      if (($occupiedByInstanceId && $this->isDifferentInstance($activityUrl, $singleProgramLine, $occupiedByInstanceId, $originalActivity))
        || (!$occupiedByInstanceId && $this->canNotBeNewInstanceOfActivity($activityUrl, $singleProgramLine, $occupiedByActivityId))
      ) {
        return ImportStepResult::error(sprintf(
          "Název '%s' už je obsazený jinou existující aktivitou %s.",
          $activityName,
          $this->importValuesDescriber->describeActivityById($occupiedByActivityId)
        ));
      }
    }
    return ImportStepResult::success(null);
  }

  public function checkStateUsability(array $sqlMappedValues, ?\Aktivita $originalActivity): ImportStepResult {
    if ($originalActivity && !$originalActivity->bezpecneEditovatelna()) {
      return ImportStepResult::error(sprintf(
        "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'.",
        $this->importValuesDescriber->describeActivity($originalActivity), $originalActivity->stav()->nazev()
      ));
    }
    $stateId = $sqlMappedValues[AktivitaSqlSloupce::STAV];
    if ($stateId === null) {
      return ImportStepResult::success(null);
    }
    $state = \Stav::zId($stateId);
    if ($state->jeNanejvysPripravenaKAktivaci()) {
      return ImportStepResult::success($state->id());
    }
    return ImportStepResult::successWithErrorLikeWarnings(
      \Stav::PRIPRAVENA,
      [sprintf(
        "Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl změněn na '%s'.",
        $state->nazev(),
        \Stav::zId(\Stav::PRIPRAVENA)->nazev()
      )]
    );
  }

  public function checkRequiredValuesForState(array $sqlMappedValues, array $tagIds): ImportStepResult {
    $stateId = $sqlMappedValues[AktivitaSqlSloupce::STAV];
    if ($stateId === null) {
      return ImportStepResult::success(null);
    }
    $state = \Stav::zId($stateId);
    if ($state->jePublikovana()) {
      $requiredFieldsForPublishingResult = $this->checkRequiredFieldsForPublishing($sqlMappedValues, $tagIds);
      if ($requiredFieldsForPublishingResult->isError()) {
        return $requiredFieldsForPublishingResult;
      }
    } elseif ($state->jePripravenaKAktivaci()) {
      $requiredFieldsForReadyForActivationResult = $this->checkRequiredFieldsForReadyToActivation($sqlMappedValues, $tagIds);
      if ($requiredFieldsForReadyForActivationResult->isError()) {
        return $requiredFieldsForReadyForActivationResult;
      }
    }
    if ($state->jeNanejvysPripravenaKAktivaci()) {
      return ImportStepResult::success($state->id());
    }
    return ImportStepResult::successWithErrorLikeWarnings(
      \Stav::PRIPRAVENA,
      [sprintf(
        "Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl změněn na '%s'.",
        $state->nazev(),
        \Stav::zId(\Stav::PRIPRAVENA)->nazev()
      )]
    );
  }

  private function checkRequiredFieldsForReadyToActivation(array $sqlMappedValues, array $tagIds): ImportStepResult {
    $requiredNonEmptyFields = [
      AktivitaSqlSloupce::NAZEV_AKCE,
      AktivitaSqlSloupce::URL_AKCE,
      AktivitaSqlSloupce::ZACATEK,
      AktivitaSqlSloupce::KONEC,
      AktivitaSqlSloupce::LOKACE,
      AktivitaSqlSloupce::POPIS_KRATKY,
      AktivitaSqlSloupce::POPIS,
      AktivitaSqlSloupce::VYBAVENI,
    ];
    $requiredZeroableFields = [
      AktivitaSqlSloupce::CENA,
      AktivitaSqlSloupce::BEZ_SLEVY,
      AktivitaSqlSloupce::NEDAVA_SLEVU,
      AktivitaSqlSloupce::KAPACITA,
      AktivitaSqlSloupce::KAPACITA_F,
      AktivitaSqlSloupce::KAPACITA_M,
    ];
    if ($sqlMappedValues[AktivitaSqlSloupce::TEAMOVA]) {
      $requiredZeroableFields[] = AktivitaSqlSloupce::TEAM_MIN;
      $requiredZeroableFields[] = AktivitaSqlSloupce::TEAM_MAX;
    }
    $missingNames = $this->getMissingRequiredFieldsForState($sqlMappedValues, $tagIds, $requiredNonEmptyFields, $requiredZeroableFields);

    if ($missingNames) {
      return ImportStepResult::error(sprintf(
        'Pro připravení k aktivaci musíš aktivitě vyplnit ještě %s.',
        implode(', ', $missingNames)
      ));
    }
    return ImportStepResult::success(null);
  }

  private function checkRequiredFieldsForPublishing(array $sqlMappedValues, array $tagIds): ImportStepResult {
    $requiredNonEmptyFields = [
      AktivitaSqlSloupce::NAZEV_AKCE,
      AktivitaSqlSloupce::URL_AKCE,
      AktivitaSqlSloupce::POPIS_KRATKY,
      AktivitaSqlSloupce::POPIS,
    ];
    $missingNames = $this->getMissingRequiredFieldsForState($sqlMappedValues, $tagIds, $requiredNonEmptyFields, []);
    if ($missingNames) {
      return ImportStepResult::error(sprintf(
        'Pro publikování musíš aktivitě vyplnit ještě %s.',
        implode(', ', $missingNames)
      ));
    }
    return ImportStepResult::success(null);
  }

  private function getMissingRequiredFieldsForState(array $sqlMappedValues, array $tagIds, array $requiredNonEmptyFields, array $requiredZeroableFields): array {
    $missingFields = [];
    foreach ($requiredNonEmptyFields as $requiredNonEmptyField) {
      if (empty($sqlMappedValues[$requiredNonEmptyField])) {
        $missingFields[] = $requiredNonEmptyField;
      }
    }
    foreach ($requiredZeroableFields as $requiredZeroableField) {
      if (!isset($sqlMappedValues[$requiredZeroableField]) || (string)$sqlMappedValues[$requiredZeroableField] === '') {
        $missingFields[] = $requiredZeroableField;
      }
    }
    if (count($tagIds) === 0) {
      $missingFields[] = 'tags';
    }
    if (false /* TODO solve image existence */) {
      $missingFields[] = 'image';
    }
    if ($missingFields) {
      $missingFieldsAsKeys = array_fill_keys($missingFields, true);
      $missingNames = array_intersect_key(self::getFieldsToNames(), $missingFieldsAsKeys);
      return array_map(static function (string $name) {
        return mb_strtolower($name, 'UTF-8');
      }, $missingNames);
    }
    return [];
  }

  private static function getFieldsToNames(): array {
    return [
      AktivitaSqlSloupce::NAZEV_AKCE => ExportAktivitSloupce::NAZEV,
      AktivitaSqlSloupce::URL_AKCE => ExportAktivitSloupce::URL,
      AktivitaSqlSloupce::POPIS_KRATKY => ExportAktivitSloupce::KRATKA_ANOTACE,
      AktivitaSqlSloupce::POPIS => ExportAktivitSloupce::DLOUHA_ANOTACE,
      'tags' => ExportAktivitSloupce::TAGY,
      'image' => ExportAktivitSloupce::OBRAZEK,
    ];
  }

  public function checkLocationByAccessibility(
    ?int $locationId,
    ?string $zacatekString,
    ?string $konecString,
    ?\Aktivita $originalActivity
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
    $locationOccupyingActivityIds = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE akce_seznam.lokace = $1
AND akce_seznam.zacatek >= $2
AND akce_seznam.konec <= $3
AND CASE
    WHEN $4 IS NULL THEN TRUE
    ELSE akce_seznam.id_akce != $4
    END
SQL
      , [$locationId, $zacatek->format(DateTimeCz::FORMAT_DB), $konec->format(DateTimeCz::FORMAT_DB), $originalActivity ? $originalActivity->id() : null]
    );
    if (count($locationOccupyingActivityIds) === 0) {
      return ImportStepResult::success($locationId);
    }
    return ImportStepResult::successWithErrorLikeWarnings(
      $locationId,
      [
        sprintf(
          'Místnost %s je někdy mezi %s a %s již zabraná %s %s. Nyní tak byla přidána už %d. aktivita do této místnosti.',
          $this->importValuesDescriber->describeLocationById($locationId),
          $zacatek->formatCasNaMinutyStandard(),
          $konec->formatCasNaMinutyStandard(),
          count($locationOccupyingActivityIds) > 1
            ? 'jinými aktivitami'
            : 'jinou aktivitou',
          implode(
            ' a ',
            array_map(
              function ($locationOccupyingActivityIds) {
                return $this->importValuesDescriber->describeActivityById((int)$locationOccupyingActivityIds);
              },
              $locationOccupyingActivityIds
            )
          ),
          count($locationOccupyingActivityIds) + 1
        ),
      ]
    );
  }

  public function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?\Aktivita $originalActivity): ImportStepResult {
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
    $errorLikeWarnings = [];
    foreach ($conflictingStorytellers as $conflictingStorytellerId => $implodedActivityIds) {
      $anotherActivityIds = explode(',', $implodedActivityIds);
      $errorLikeWarnings[] = sprintf(
        'Vypravěč %s je v čase od %s do %s na %s %s. K aktivitě nebyl přiřazen.',
        $this->importValuesDescriber->describeUserById((int)$conflictingStorytellerId),
        $zacatek->formatCasStandard(),
        $konec->formatCasStandard(),
        count($anotherActivityIds) === 1
          ? 'aktivitě'
          : 'aktivitách',
        implode(' a ', array_map(function ($anotherActivityId) {
          return $this->importValuesDescriber->describeActivityById((int)$anotherActivityId);
        }, $anotherActivityIds))
      );
    }
    return ImportStepResult::successWithErrorLikeWarnings(
      array_diff($storytellersIds, array_keys($occupiedStorytellers)),
      $errorLikeWarnings
    );
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

  public function checkTeamCapacityRange(bool $isTeamActivity, ?int $minimalTeamCapacity, ?int $maximalTeamCapacity): ImportStepResult {
    if (!$isTeamActivity) {
      return ImportStepResult::success(null);
    }
    if ($minimalTeamCapacity > $maximalTeamCapacity) {
      return ImportStepResult::error(sprintf(
        'Minimální týmová kapacita %d nemůže být větší než maximální %d.',
        $minimalTeamCapacity,
        $maximalTeamCapacity
      ));
    }
    return ImportStepResult::success(null);
  }

  public function checkNonTeamCapacity(bool $isTeamActivity, ?int $unisexCapacity, ?int $menCapacity, ?int $womenCapacity): ImportStepResult {
    if ($isTeamActivity) {
      return ImportStepResult::success(null);
    }
    if (($unisexCapacity ?: 0) + ($menCapacity ?: 0) + ($womenCapacity ?: 0) === 0) {
      return ImportStepResult::successWithWarnings(
        null,
        [
          'Kapacita aktivity by neměla být nulová. Alespoň jedna z kapacit unisex, mužská nebo ženská by měly být vyplněné.',
        ]
      );
    }
    return ImportStepResult::success(null);
  }
}
