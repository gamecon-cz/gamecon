<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
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
  /**
   * @var array|\Aktivita[]|null[]
   */
  private $originalActivities = [];

  public function __construct(
    ImportValuesDescriber $importValuesDescriber,
    ImportObjectsContainer $importObjectsContainer,
    int $currentYear,
    string $storytellersPermissionsUrl
  ) {
    $this->importValuesDescriber = $importValuesDescriber;
    $this->currentYear = $currentYear;
    $this->importObjectsContainer = $importObjectsContainer;
    $this->storytellersPermissionsUrl = $storytellersPermissionsUrl;
  }

  public function sanitizeValues(\Typ $singleProgramLine, array $activityValues): ImportStepResult {
    $sanitizedValues = [];
    $stepsResults = [];
    $warnings = [];
    $errorLikeWarnings = [];

    $originalActivity = null;
    $originalActivityResult = $this->getValidatedOriginalActivity($activityValues);
    if ($originalActivityResult->isError()) {
      return ImportStepResult::error($originalActivityResult->getError());
    }
    $originalActivity = $originalActivityResult->getSuccess();

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

    $programLineIdResult = $this->getValidatedProgramLineId($activityValues, $singleProgramLine);
    if ($programLineIdResult->isError()) {
      return ImportStepResult::error($programLineIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TYP] = $programLineIdResult->getSuccess();
    $stepsResults[] = $programLineIdResult;
    unset($programLineIdResult);

    $activityUrlResult = $this->getValidatedUrl($activityValues, $singleProgramLine, $originalActivity);
    if ($activityUrlResult->isError()) {
      return ImportStepResult::error($activityUrlResult->getError());
    }
    $activityUrl = $activityUrlResult->getSuccess();
    $sanitizedValues[AktivitaSqlSloupce::URL_AKCE] = $activityUrl;
    $stepsResults[] = $activityUrlResult;
    unset($activityUrlResult);

    $parentActivityResult = $this->getValidatedParentActivity($activityUrl, $singleProgramLine);
    if ($parentActivityResult->isError()) {
      return ImportStepResult::error($parentActivityResult->getError());
    }
    $parentActivity = $parentActivityResult->getSuccess();
    $stepsResults[] = $parentActivityResult;
    unset($parentActivityResult);

    $activityNameResult = $this->getValidatedActivityName($activityValues, $activityUrl, $singleProgramLine, $originalActivity, $parentActivity);
    if ($activityNameResult->isError()) {
      return ImportStepResult::error($activityNameResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::NAZEV_AKCE] = $activityNameResult->getSuccess();
    $stepsResults[] = $activityNameResult;
    unset($activityNameResult);

    $shortAnnotationResult = $this->getValidatedShortAnnotation($activityValues, $originalActivity, $parentActivity);
    if ($shortAnnotationResult->isError()) {
      return ImportStepResult::error($shortAnnotationResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS_KRATKY] = $shortAnnotationResult->getSuccess();
    $stepsResults[] = $shortAnnotationResult;
    unset($shortAnnotationResult);

    $tagIdsResult = $this->getValidatedTagIds($activityValues, $originalActivity, $parentActivity);
    if ($tagIdsResult->isError()) {
      return ImportStepResult::error($tagIdsResult->getError());
    }
    $tagIds = $tagIdsResult->getSuccess();
    $stepsResults[] = $tagIdsResult;
    unset($tagIdsResult);

    $longAnnotationResult = $this->getValidatedLongAnnotation($activityValues, $originalActivity, $parentActivity);
    if ($longAnnotationResult->isError()) {
      return ImportStepResult::error($longAnnotationResult->getError());
    }
    $longAnnotation = $longAnnotationResult->getSuccess();
    $stepsResults[] = $longAnnotationResult;
    unset($longAnnotationResult);

    $activityStartResult = $this->getValidatedStart($activityValues, $originalActivity, $parentActivity);
    if ($activityStartResult->isError()) {
      return ImportStepResult::error($activityStartResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ZACATEK] = $activityStartResult->getSuccess();
    $stepsResults[] = $activityStartResult;
    unset($activityStartResult);

    $activityEndResult = $this->getValidatedEnd($activityValues, $originalActivity, $parentActivity);
    if ($activityEndResult->isError()) {
      return ImportStepResult::error($activityEndResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KONEC] = $activityEndResult->getSuccess();
    $stepsResults[] = $activityEndResult;
    unset($activityEndResult);

    $locationIdResult = $this->getValidatedLocationId($activityValues, $originalActivity, $parentActivity);
    if ($locationIdResult->isError()) {
      return ImportStepResult::error($locationIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::LOKACE] = $locationIdResult->getSuccess();
    $stepsResults[] = $locationIdResult;
    unset($locationIdResult);

    $storytellersIdsResult = $this->getValidatedStorytellersIds($activityValues, $originalActivity, $parentActivity);
    if ($storytellersIdsResult->isError()) {
      return ImportStepResult::error($storytellersIdsResult->getError());
    }
    $storytellersIds = $storytellersIdsResult->getSuccess();
    $stepsResults[] = $storytellersIdsResult;
    unset($storytellersIdsResult);

    $unisexCapacityResult = $this->getValidatedUnisexCapacity($activityValues, $originalActivity, $parentActivity);
    if ($unisexCapacityResult->isError()) {
      return ImportStepResult::error($unisexCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA] = $unisexCapacityResult->getSuccess();
    $stepsResults[] = $unisexCapacityResult;
    unset($unisexCapacityResult);

    $menCapacityResult = $this->getValidatedMenCapacity($activityValues, $originalActivity, $parentActivity);
    if ($menCapacityResult->isError()) {
      return ImportStepResult::error($menCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_M] = $menCapacityResult->getSuccess();
    $stepsResults[] = $menCapacityResult;
    unset($menCapacityResult);

    $womenCapacityResult = $this->getValidatedWomenCapacity($activityValues, $originalActivity, $parentActivity);
    if ($womenCapacityResult->isError()) {
      return ImportStepResult::error($womenCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_F] = $womenCapacityResult->getSuccess();
    $stepsResults[] = $womenCapacityResult;
    unset($womenCapacityResult);

    $forTeamResult = $this->getValidatedForTeam($activityValues, $originalActivity, $parentActivity);
    if ($forTeamResult->isError()) {
      return ImportStepResult::error($forTeamResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAMOVA] = $forTeamResult->getSuccess();
    $stepsResults[] = $forTeamResult;
    unset($forTeamResult);

    $minimalTeamCapacityResult = $this->getValidatedMinimalTeamCapacity($activityValues, $originalActivity, $parentActivity);
    if ($minimalTeamCapacityResult->isError()) {
      return ImportStepResult::error($minimalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MIN] = $minimalTeamCapacityResult->getSuccess();
    $stepsResults[] = $minimalTeamCapacityResult;
    unset($minimalTeamCapacityResult);

    $maximalTeamCapacityResult = $this->getValidatedMaximalTeamCapacity($activityValues, $originalActivity, $parentActivity);
    if ($maximalTeamCapacityResult->isError()) {
      return ImportStepResult::error($maximalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MAX] = $maximalTeamCapacityResult->getSuccess();
    $stepsResults[] = $maximalTeamCapacityResult;
    unset($maximalTeamCapacityResult);

    $priceResult = $this->getValidatedPrice($activityValues, $originalActivity, $parentActivity);
    if ($priceResult->isError()) {
      return ImportStepResult::error($priceResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::CENA] = $priceResult->getSuccess();
    $stepsResults[] = $priceResult;
    unset($priceResult);

    $withoutDiscountResult = $this->getValidatedWithoutDiscount($activityValues, $originalActivity, $parentActivity);
    if ($withoutDiscountResult->isError()) {
      return ImportStepResult::error($withoutDiscountResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::BEZ_SLEVY] = $withoutDiscountResult->getSuccess();
    $stepsResults[] = $withoutDiscountResult;
    unset($withoutDiscountResult);

    $equipmentResult = $this->getValidatedEquipment($activityValues, $originalActivity, $parentActivity);
    if ($equipmentResult->isError()) {
      return ImportStepResult::error($equipmentResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::VYBAVENI] = $equipmentResult->getSuccess();
    $stepsResults[] = $equipmentResult;
    unset($equipmentResult);

    $stateIdResult = $this->getValidatedStateId($activityValues, $originalActivity, $parentActivity);
    if ($stateIdResult->isError()) {
      return ImportStepResult::error($stateIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::STAV] = $stateIdResult->getSuccess();
    $stepsResults[] = $stateIdResult;
    unset($stateIdResult);

    $yearResult = $this->getValidatedYear($originalActivity, $parentActivity);
    if ($yearResult->isError()) {
      return ImportStepResult::error($yearResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ROK] = $yearResult->getSuccess();
    $stepsResults[] = $yearResult;
    unset($yearResult);

    $instanceIdResult = $this->getValidatedInstanceId($originalActivity, $parentActivity);
    if ($instanceIdResult->isError()) {
      return ImportStepResult::error($instanceIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::PATRI_POD] = $instanceIdResult->getSuccess();
    $stepsResults[] = $instanceIdResult;
    unset($instanceIdResult);

    $potentialImageUrlsResult = $this->getPotentialImageUrls($activityValues, $activityUrl);
    if ($potentialImageUrlsResult->isError()) {
      return ImportStepResult::error($potentialImageUrlsResult->getError());
    }
    $potentialImageUrls = $potentialImageUrlsResult->getSuccess();
    $stepsResults[] = $potentialImageUrlsResult;
    unset($potentialImageUrlsResult);

    foreach ($stepsResults as $stepsResult) {
      ['warnings' => $warnings, 'errorLikeWarnings' => $errorLikeWarnings] = $this->collectWarnings($stepsResult, $warnings, $errorLikeWarnings);
    }

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
    );
  }

  private function collectWarnings(ImportStepResult $importStepResult, array $warnings, array $errorLikeWarnings): array {
    return [
      'warnings' => array_merge($warnings, $importStepResult->getWarnings()),
      'errorLikeWarnings' => array_merge($errorLikeWarnings, $importStepResult->getErrorLikeWarnings()),
    ];
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

  private function getPotentialImageUrls(array $activityValues, string $activityUrl): ImportStepResult {
    $imageUrl = $activityValues[ExportAktivitSloupce::OBRAZEK] ?? null;
    if (!$imageUrl) {
      return ImportStepResult::success([]);
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

  private function getValidatedStateId(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $stateValue = $activityValues[ExportAktivitSloupce::STAV] ?? null;
    if ((string)$stateValue === '') {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity && $sourceActivity->idStavu() !== null
        ? $sourceActivity->idStavu()
        : \Stav::NOVA
      );
    }
    $state = $this->importObjectsContainer->getStateFromValue((string)$stateValue);
    if ($state) {
      if ($state->jeNanejvysPripravenaKAktivaci()) {
        return ImportStepResult::success($state->id());
      }
      return ImportStepResult::successWithErrorLikeWarnings(
        \Stav::PRIPRAVENA,
        [sprintf(
          "%s: Aktivovat musíš aktivity ručně. Požadovaný stav '%s' byl nahrán jako '%s'.",
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
          $state->nazev(),
          \Stav::zId(\Stav::PRIPRAVENA)->nazev()
        )]
      );
    }
    return ImportStepResult::error(sprintf(
      "%s: neznámý stav '%s'",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $stateValue
    ));
  }

  private function getValidatedEquipment(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $equipmentValue = $activityValues[ExportAktivitSloupce::VYBAVENI] ?? null;
    if ((string)$equipmentValue === '') {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity
        ? $sourceActivity->vybaveni()
        : ''
      );
    }
    return ImportStepResult::success($equipmentValue);
  }

  private function getValidatedMinimalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$minimalTeamCapacityValue === '') {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity
        ? $sourceActivity->tymMinKapacita()
        : 0
      );
    }
    $minimalTeamCapacity = (int)$minimalTeamCapacityValue;
    if ($minimalTeamCapacity > 0) {
      return ImportStepResult::success($minimalTeamCapacity);
    }
    if ((string)$minimalTeamCapacityValue === '0') {
      return ImportStepResult::success(0);
    }
    return ImportStepResult::error(sprintf(
      "%s: Podivná minimální kapacita týmu '%s'. Očekáváme celé kladné číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $minimalTeamCapacityValue
    ));
  }

  private function getValidatedMaximalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$maximalTeamCapacityValue === '') {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity
        ? $sourceActivity->tymMaxKapacita()
        : 0
      );
    }
    $maximalTeamCapacity = (int)$maximalTeamCapacityValue;
    if ($maximalTeamCapacity > 0) {
      return ImportStepResult::success($maximalTeamCapacity);
    }
    if ((string)$maximalTeamCapacityValue === '0') {
      return ImportStepResult::success(0);
    }
    return ImportStepResult::error(sprintf(
      "%s: Podivná maximální kapacita týmu '%s'. Očekáváme celé kladné číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $maximalTeamCapacityValue
    ));
  }

  private function getValidatedForTeam(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      "%s: podivný zápis, zda je aktivita týmová '%s'. Očekáváme pouze 1, 0, ano, ne.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $forTeamValue
    ));
  }

  private function getValidatedStorytellersIds(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? null;
    if (!$storytellersString) {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity
        ? $sourceActivity->getOrganizatoriIds()
        : []
      );
    }
    $storytellersIds = [];
    $invalidStorytellersValues = [];
    $notStorytellers = [];
    $storytellersValues = array_map('trim', explode(',', $storytellersString));
    foreach ($storytellersValues as $storytellerValue) {
      $storyteller = $this->importObjectsContainer->getUserFromValue($storytellerValue);
      if (!$storyteller) {
        $invalidStorytellersValues[] = $storytellerValue;
      } elseif (!$storyteller->jeOrganizator()) {
        $notStorytellers[] = $storyteller;
      } else {
        $storytellersIds[] = $storyteller->id();
      }
    }
    $errorLikeWarnings = [];
    if ($invalidStorytellersValues) {
      $errorLikeWarnings[] = sprintf(
        '%s: Neznámí uživatelé %s. Byli vynecháni.',
        $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
        implode(',', array_map(static function (string $invalidStorytellerValue) {
          return "'$invalidStorytellerValue'";
        }, $invalidStorytellersValues))
      );
    }
    if ($notStorytellers) {
      $notStorytellersString = implode(',', array_map(function (\Uzivatel $user) {
        return $this->importValuesDescriber->describeUser($user);
      }, $notStorytellers));
      $notStorytellersHtml = htmlentities($notStorytellersString);
      $errorLikeWarnings[] = sprintf(<<<HTML
        '%s: Uživatelé nejsou <a href="{$this->storytellersPermissionsUrl}" target="_blank">vypravěči</a>: {$notStorytellersHtml}. Byli vynecháni.
HTML
        , $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity)
      );
    }
    return ImportStepResult::successWithErrorLikeWarnings($storytellersIds, $errorLikeWarnings);
  }

  private function getValidatedLongAnnotation(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    if (!empty($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE])) {
      return ImportStepResult::success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE]);
    }
    $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
    return ImportStepResult::success($sourceActivity
      ? $sourceActivity->popis()
      : ''
    );
  }

  private function getValidatedTagIds(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
    $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
    if ($tagsString === '' && $sourceActivity) {
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
          E_USER_WARNING,
          sprintf('There are some strange tags coming from activity %s, which are unknown %s', $sourceActivity->id(), implode(',', $invalidTagsValues))
        );
      }
      return ImportStepResult::success($tagIds);
    }
    $tagIds = [];
    $invalidTagsValues = [];
    $tagsValues = array_map('trim', explode(',', $tagsString));
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
          '%s: neznámé tagy %s',
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
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

  private function getValidatedShortAnnotation(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    if (!empty($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE])) {
      return ImportStepResult::success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]);
    }
    $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
    return ImportStepResult::success($sourceActivity
      ? $sourceActivity->kratkyPopis()
      : ''
    );
  }

  private function getValidatedInstanceId(?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
    return ImportStepResult::success($sourceActivity
      ? $sourceActivity->patriPod()
      : null
    );
  }

  private function getValidatedYear(?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
        'Aktivita %s je pro ročník %d, ale teď je ročník %d.',
        $this->importValuesDescriber->describeActivity($sourceActivity),
        $year,
        $this->currentYear
      ));
    }
    return ImportStepResult::success($this->currentYear);
  }

  private function getValidatedWithoutDiscount(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      "%s: Podivný zápis 'bez slevy': '%s'. Očekáváme pouze 1, 0 nebo ano, ne.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $withoutDiscountValue
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

  private function getValidatedUnisexCapacity(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      "%s: Podivná unisex kapacita '%s'. Očekáváme celé kladné číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $capacityValue
    ));
  }

  private function getValidatedMenCapacity(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      "%s: Podivná kapacita mužů '%s'. Očekáváme celé kladné číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $capacityValue
    ));
  }

  private function getValidatedWomenCapacity(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      "%s: Podivná kapacita žen '%s'. Očekáváme celé kladné číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $capacityValue
    ));
  }

  private function getValidatedPrice(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
    if ((string)$priceValue === '') {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return ImportStepResult::success($sourceActivity
        ? $sourceActivity->cenaZaklad()
        : 0.0
      );
    }
    $priceFloat = (float)$priceValue;
    if ($priceFloat !== 0.0) {
      return ImportStepResult::success($priceFloat);
    }
    if ((string)$priceFloat === '0' || (string)$priceFloat === '0.0') {
      return ImportStepResult::success(0.0);
    }
    return ImportStepResult::error(sprintf(
      "%s: Podivná cena aktivity '%s'. Očekáváme číslo.",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $priceValue
    ));
  }

  private function getValidatedLocationId(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
    return ImportStepResult::successWithWarnings(
      null,
      [
        sprintf(
          "%s: Neznámá místnost '%s'. Aktivita je bez místnosti.",
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
          $locationValue
        ),
      ]
    );
  }

  private function getValidatedStart(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      return ImportStepResult::error(
        sprintf(
          '%s: Aktivita má sice uvedený začátek (%s), ale chybí u ní den.',
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
          $activityValues[ExportAktivitSloupce::ZACATEK]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK]);
  }

  private function getValidatedEnd(array $activityValues, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
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
      return ImportStepResult::error(
        sprintf(
          '%s: aktivita má sice uvedený konec (%s), ale chybí u ní den',
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
          $activityValues[ExportAktivitSloupce::KONEC]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::KONEC]);
  }

  private function createDateTimeFromRangeBorder(int $year, string $dayName, string $hoursAndMinutes): ImportStepResult {
    try {
      $date = DateTimeGamecon::denKolemZacatkuGameconuProRok($dayName, $year);
    } catch (\Exception $exception) {
      return ImportStepResult::error(sprintf("Nepodařilo se vytvořit datum z roku %d, dne '%s' a času '%s'. Chybný formát datumu. Detail: %s", $year, $dayName, $hoursAndMinutes, $exception->getMessage()));
    }

    if (!preg_match('~^(?<hours>\d+)(\s*:\s*(?<minutes>\d+))?$~', $hoursAndMinutes, $timeMatches)) {
      return ImportStepResult::error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát času '%s'.", $year, $dayName, $hoursAndMinutes, $hoursAndMinutes));
    }
    $hours = (int)$timeMatches['hours'];
    $minutes = (int)($timeMatches['minutes'] ?? 0);
    $dateTime = $date->setTime($hours, $minutes, 0, 0);
    if (!$dateTime) {
      return ImportStepResult::error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát.", $year, $dayName, $hoursAndMinutes));
    }
    return ImportStepResult::success($dateTime->formatDb());
  }

  private function getValidatedUrl(array $activityValues, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ImportStepResult {
    $activityUrl = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if (!$activityUrl) {
      if ($originalActivity) {
        return ImportStepResult::success($originalActivity->urlId());
      }
      if (empty($activityValues[ExportAktivitSloupce::NAZEV])) {
        return ImportStepResult::error(sprintf(
          '%s: Nová aktivita nemá ani URL, ani název, ze kterého by URL šlo vytvořit.',
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity)
        ));
      }
      $activityUrl = $this->toUrl($activityValues[ExportAktivitSloupce::NAZEV]);
    }
    $activityUrl = $this->toUrl($activityUrl);
    $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, patri_pod
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
      ,
      [$activityUrl, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0]
    );
    if ($occupiedByActivities) {
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
            $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
            $activityUrl,
            empty($activityValues[ExportAktivitSloupce::URL])
              ? ' (odhadnutá z názvu)'
              : '',
            $this->importValuesDescriber->describeActivityById($occupiedByActivityId)
          ));
        }
      }
    }
    return ImportStepResult::success($activityUrl);
  }

  private function getValidatedParentActivity(string $url, \Typ $singleProgramLine): ImportStepResult {
    return ImportStepResult::success(\Aktivita::moznaHlavniAktivitaPodleUrl($url, $this->currentYear, $singleProgramLine->id()));
  }

  private function getInstanceIdByUrl(string $url, int $programLineId): ?int {
    return \Aktivita::idExistujiciInstancePodleUrl($url, $this->currentYear, $programLineId);
  }

  private function toUrl(string $value): string {
    $sanitized = strtolower(odstranDiakritiku($value));
    return preg_replace('~\W+~', '-', $sanitized);
  }

  private function getValidatedActivityName(array $activityValues, string $activityUrl, \Typ $singleProgramLine, ?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ImportStepResult {
    $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
    if (!$activityNameValue) {
      $sourceActivity = $this->getSourceActivity($originalActivity, $parentActivity);
      return $sourceActivity
        ? ImportStepResult::success($sourceActivity->nazev())
        : ImportStepResult::error(sprintf(
          '%s: chybí povinný název.',
          $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity)
        ));
    }
    $nameOccupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2 AND typ = $3 AND id_akce != $4
SQL
      , [$activityNameValue, $this->currentYear, $singleProgramLine->id(), $originalActivity ? $originalActivity->id() : 0]
    );
    if ($nameOccupiedByActivities) {
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
            $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
            $activityNameValue,
            $this->importValuesDescriber->describeActivityById($occupiedByActivityId)
          ));
        }
      }
    }
    return ImportStepResult::success($activityNameValue);
  }

  private function getSourceActivity(?\Aktivita $originalActivity, ?\Aktivita $parentActivity): ?\Aktivita {
    return $originalActivity ?: $parentActivity;
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

  private function getValidatedProgramLineId(array $activityValues, \Typ $singleProgramLine): ImportStepResult {
    $programLineValue = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if ((string)$programLineValue === '') {
      return ImportStepResult::success($singleProgramLine->id());
    }
    $programLine = $this->importObjectsContainer->getProgramLineFromValue((string)$programLineValue);
    if (!$programLine) {
      return ImportStepResult::error(sprintf(
        "%s: Neznámá programová linie '%s'.",
        $this->importValuesDescriber->describeActivityByInputValues($activityValues, null),
        $programLineValue
      ));
    }
    if ($programLine->id() !== $singleProgramLine->id()) {
      return ImportStepResult::error(sprintf(
        '%s: Importovat lze pouze jednu programovou linii. Aktivita má navíc %s.',
        $this->importValuesDescriber->describeActivityByInputValues($activityValues, null),
        $programLineValue
      ));
    }
    return ImportStepResult::success($programLine->id());
  }
}
