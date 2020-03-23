<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Cas\DateTimeCz;

class ImportValuesChecker
{
  /**
   * @var ImportValuesDescriber
   */
  private $importValuesDescriber;
  /**
   * @var int
   */
  private $currentYear;

  public function __construct(
    int $currentYear,
    ImportValuesDescriber $importValuesDescriber
  ) {
    $this->importValuesDescriber = $importValuesDescriber;
    $this->currentYear = $currentYear;
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
          "%s: URL '%s'%s už je obsazena jinou existující aktivitou %s.",
          $this->importValuesDescriber->describeActivityBySqlMappedValues($sqlMappedValues, $originalActivity),
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
          "%s: název '%s' už je obsazený jinou existující aktivitou %s.",
          $this->importValuesDescriber->describeActivityBySqlMappedValues($sqlMappedValues, $originalActivity),
          $activityName,
          $this->importValuesDescriber->describeActivityById($occupiedByActivityId)
        ));
      }
    }
    return ImportStepResult::success(null);
  }

  public function checkStateUsability(array $values, ?\Aktivita $originalActivity): ImportStepResult {
    $stateId = $values[AktivitaSqlSloupce::STAV];
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
        "%s: Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl nahrán jako '%s'.",
        $this->importValuesDescriber->describeActivityBySqlMappedValues($values, $originalActivity),
        $state->nazev(),
        \Stav::zId(\Stav::PRIPRAVENA)->nazev()
      )]
    );
  }

  public function checkLocationByAccessibility(
    ?int $locationId,
    ?string $zacatekString,
    ?string $konecString,
    ?\Aktivita $originalActivity,
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
          '%s: Místnost %s je někdy mezi %s a %s již zabraná jinou aktivitou %s. Nahrávaná aktivita je tak už %d. aktivitou v této místnosti.',
          $this->importValuesDescriber->describeActivityBySqlMappedValues($values, $originalActivity),
          $this->importValuesDescriber->describeLocationById($locationId),
          $zacatek->formatCasNaMinutyStandard(),
          $konec->formatCasNaMinutyStandard(),
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

  public function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?\Aktivita $originalActivity, array $values): ImportStepResult {
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
      $activityIds = explode(',', $implodedActivityIds);
      $errorLikeWarnings[] = sprintf(
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

}
