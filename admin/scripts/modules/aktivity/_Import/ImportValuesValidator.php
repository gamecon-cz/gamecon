<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Cas\DateTimeGamecon;

class ImportValuesValidator
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

  public function validateValues(\Typ $singleProgramLine, array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $sanitizedValues = [];
    $warnings = [];
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
    unset($programLineIdResult);

    $activityUrlResult = $this->getValidatedUrl($activityValues, $singleProgramLine, $originalActivity);
    if ($activityUrlResult->isError()) {
      return ImportStepResult::error($activityUrlResult->getError());
    }
    $activityUrl = $activityUrlResult->getSuccess();
    $sanitizedValues[AktivitaSqlSloupce::URL_AKCE] = $activityUrl;
    unset($activityUrlResult);

    $activityNameResult = $this->getValidatedActivityName($activityValues, $activityUrl, $singleProgramLine, $originalActivity);
    if ($activityNameResult->isError()) {
      return ImportStepResult::error($activityNameResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::NAZEV_AKCE] = $activityNameResult->getSuccess();
    unset($activityNameResult);

    $shortAnnotationResult = $this->getValidatedShortAnnotation($activityValues, $originalActivity);
    if ($shortAnnotationResult->isError()) {
      return ImportStepResult::error($shortAnnotationResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS_KRATKY] = $shortAnnotationResult->getSuccess();
    unset($shortAnnotationResult);

    $tagIdsResult = $this->getValidatedTagIds($activityValues, $originalActivity);
    if ($tagIdsResult->isError()) {
      return ImportStepResult::error($tagIdsResult->getError());
    }
    $tagIds = $tagIdsResult->getSuccess();
    unset($tagIdsResult);

    $longAnnotationResult = $this->getValidatedLongAnnotation($activityValues, $originalActivity);
    if ($longAnnotationResult->isError()) {
      return ImportStepResult::error($longAnnotationResult->getError());
    }
    $longAnnotation = $longAnnotationResult->getSuccess();
    unset($longAnnotationResult);

    $activityStartResult = $this->getValidatedStart($activityValues, $originalActivity);
    if ($activityStartResult->isError()) {
      return ImportStepResult::error($activityStartResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ZACATEK] = $activityStartResult->getSuccess();
    unset($activityStartResult);

    $activityEndResult = $this->getValidatedEnd($activityValues, $originalActivity);
    if ($activityEndResult->isError()) {
      return ImportStepResult::error($activityEndResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KONEC] = $activityEndResult->getSuccess();
    unset($activityEndResult);

    $locationIdResult = $this->getValidatedLocationId($activityValues, $originalActivity);
    if ($locationIdResult->isError()) {
      return ImportStepResult::error($locationIdResult->getError());
    }
    if ($locationIdResult->hasWarnings()) {
      foreach ($locationIdResult->getWarnings() as $warning) {
        $warnings[] = $warning;
      }
    }
    $sanitizedValues[AktivitaSqlSloupce::LOKACE] = $locationIdResult->getSuccess();
    unset($locationIdResult);

    $storytellersIdsResult = $this->getValidatedStorytellersIds($activityValues, $originalActivity);
    if ($storytellersIdsResult->isError()) {
      return ImportStepResult::error($storytellersIdsResult->getError());
    }
    $storytellersIds = $storytellersIdsResult->getSuccess();
    unset($storytellersIdsResult);

    $unisexCapacityResult = $this->getValidatedUnisexCapacity($activityValues, $originalActivity);
    if ($unisexCapacityResult->isError()) {
      return ImportStepResult::error($unisexCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA] = $unisexCapacityResult->getSuccess();
    unset($unisexCapacityResult);

    $menCapacityResult = $this->getValidatedMenCapacity($activityValues, $originalActivity);
    if ($menCapacityResult->isError()) {
      return ImportStepResult::error($menCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_M] = $menCapacityResult->getSuccess();
    unset($menCapacityResult);

    $womenCapacityResult = $this->getValidatedWomenCapacity($activityValues, $originalActivity);
    if ($womenCapacityResult->isError()) {
      return ImportStepResult::error($womenCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_F] = $womenCapacityResult->getSuccess();
    unset($womenCapacityResult);

    $forTeamResult = $this->getValidatedForTeam($activityValues, $originalActivity);
    if ($forTeamResult->isError()) {
      return ImportStepResult::error($forTeamResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAMOVA] = $forTeamResult->getSuccess();
    unset($forTeamResult);

    $minimalTeamCapacityResult = $this->getValidatedMinimalTeamCapacity($activityValues, $originalActivity);
    if ($minimalTeamCapacityResult->isError()) {
      return ImportStepResult::error($minimalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MIN] = $minimalTeamCapacityResult->getSuccess();
    unset($minimalTeamCapacityResult);

    $maximalTeamCapacityResult = $this->getValidatedMaximalTeamCapacity($activityValues, $originalActivity);
    if ($maximalTeamCapacityResult->isError()) {
      return ImportStepResult::error($maximalTeamCapacityResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MAX] = $maximalTeamCapacityResult->getSuccess();
    unset($maximalTeamCapacityResult);

    $priceResult = $this->getValidatedPrice($activityValues, $originalActivity);
    if ($priceResult->isError()) {
      return ImportStepResult::error($priceResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::CENA] = $priceResult->getSuccess();
    unset($priceResult);

    $withoutDiscountResult = $this->getValidatedWithoutDiscount($activityValues, $originalActivity);
    if ($withoutDiscountResult->isError()) {
      return ImportStepResult::error($withoutDiscountResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::BEZ_SLEVY] = $withoutDiscountResult->getSuccess();
    unset($withoutDiscountResult);

    $equipmentResult = $this->getValidatedEquipment($activityValues, $originalActivity);
    if ($equipmentResult->isError()) {
      return ImportStepResult::error($equipmentResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::VYBAVENI] = $equipmentResult->getSuccess();
    unset($equipmentResult);

    $stateIdResult = $this->getValidatedStateId($activityValues, $originalActivity);
    if ($stateIdResult->isError()) {
      return ImportStepResult::error($stateIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::STAV] = $stateIdResult->getSuccess();
    unset($stateIdResult);

    $yearResult = $this->getValidatedYear($originalActivity);
    if ($yearResult->isError()) {
      return ImportStepResult::error($yearResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::ROK] = $yearResult->getSuccess();
    unset($yearResult);

    $instanceIdResult = $this->getValidatedInstanceId($originalActivity);
    if ($instanceIdResult->isError()) {
      return ImportStepResult::error($instanceIdResult->getError());
    }
    $sanitizedValues[AktivitaSqlSloupce::PATRI_POD] = $instanceIdResult->getSuccess();
    unset($instanceIdResult);

    $potentialImageUrlsResult = $this->getPotentialImageUrls($activityValues, $activityUrl);
    if ($potentialImageUrlsResult->isError()) {
      return ImportStepResult::error($potentialImageUrlsResult->getError());
    }
    $potentialImageUrls = $potentialImageUrlsResult->getSuccess();
    unset($potentialImageUrlsResult);

    if ($originalActivity && $originalActivity->urlId() === 'apocalypse-world42') {
      var_dump($sanitizedValues);die;
    }

    return ImportStepResult::successWithWarnings(
      [
        'values' => $sanitizedValues,
        'longAnnotation' => $longAnnotation,
        'storytellersIds' => $storytellersIds,
        'tagIds' => $tagIds,
        'potentialImageUrls' => $potentialImageUrls,
      ],
      $warnings
    );
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

  private function getValidatedStateId(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $stateValue = $activityValues[ExportAktivitSloupce::STAV] ?? null;
    if ((string)$stateValue === '') {
      return ImportStepResult::success($originalActivity && $originalActivity->stav()
        ? $originalActivity->stav()->id()
        : \Stav::NOVA
      );
    }
    $state = $this->importObjectsContainer->getStateFromValue((string)$stateValue);
    if ($state) {
      return ImportStepResult::success($state->id());
    }
    return ImportStepResult::error(sprintf(
      "%s: neznámý stav '%s'",
      $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
      $stateValue
    ));
  }

  private function getValidatedEquipment(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $equipmentValue = $activityValues[ExportAktivitSloupce::VYBAVENI] ?? null;
    if ((string)$equipmentValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->vybaveni()
        : ''
      );
    }
    return ImportStepResult::success($equipmentValue);
  }

  private function getValidatedMinimalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$minimalTeamCapacityValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->tymMinKapacita()
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

  private function getValidatedMaximalTeamCapacity(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$maximalTeamCapacityValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->tymMaxKapacita()
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

  private function getValidatedForTeam(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $forTeamValue = $activityValues[ExportAktivitSloupce::JE_TYMOVA] ?? null;
    if ((string)$forTeamValue === '') {
      return ImportStepResult::success(
        $originalActivity && $originalActivity->tymova()
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

  private function getValidatedStorytellersIds(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? null;
    if (!$storytellersString) {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->getOrganizatoriIds()
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
    $errors = [];
    if ($invalidStorytellersValues) {
      $errors[] = sprintf(
        'neznámí uživatelé %s',
        implode(',', array_map(static function (string $invalidStorytellerValue) {
          return "'$invalidStorytellerValue'";
        }, $invalidStorytellersValues))
      );
    }
    if ($notStorytellers) {
      $errors[] = sprintf(
        'uživatelé nejsou <a href="%s" target="_blank">vypravěči</a>: %s',
        $this->storytellersPermissionsUrl,
        implode(',', array_map(function (\Uzivatel $user) {
          return $this->importValuesDescriber->describeUser($user);
        }, $notStorytellers))
      );
    }
    if ($errors) {
      return ImportStepResult::error(sprintf(
        '%s: %s',
        $this->importValuesDescriber->describeActivityByInputValues($activityValues, $originalActivity),
        implode('; ', $errors)
      ));
    }
    return ImportStepResult::success($storytellersIds);
  }

  private function getValidatedLongAnnotation(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    if (!empty($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE])) {
      return ImportStepResult::success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE]);
    }
    return ImportStepResult::success($originalActivity
      ? $originalActivity->popis()
      : ''
    );
  }

  private function getValidatedTagIds(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
    if ($tagsString === '' && $originalActivity) {
      $tagIds = [];
      $invalidTagsValues = [];
      foreach ($originalActivity->tagy() as $tagValue) {
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
          sprintf('There are some strange tags coming from activity %s, which are unknown %s', $originalActivity->id(), implode(',', $invalidTagsValues))
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

  private function getValidatedShortAnnotation(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    if (!empty($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE])) {
      return ImportStepResult::success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]);
    }
    return ImportStepResult::success($originalActivity
      ? $originalActivity->kratkyPopis()
      : ''
    );
  }

  private function getValidatedInstanceId(?\Aktivita $originalActivity): ImportStepResult {
    return ImportStepResult::success(
      $originalActivity
        ? $originalActivity->patriPod()
        : null
    );
  }

  private function getValidatedYear(?\Aktivita $originalActivity): ImportStepResult {
    if (!$originalActivity) {
      return ImportStepResult::success($this->currentYear);
    }
    $year = $originalActivity->zacatek()
      ? (int)$originalActivity->zacatek()->format('Y')
      : null;
    if (!$year) {
      $year = $originalActivity->konec()
        ? (int)$originalActivity->konec()->format('Y')
        : null;
    }
    if ($year && $year !== $this->currentYear) {
      return ImportStepResult::error(sprintf(
        'Aktivita %s je pro ročník %d, ale teď je ročník %d.',
        $this->importValuesDescriber->describeActivity($originalActivity),
        $year,
        $this->currentYear
      ));
    }
    return ImportStepResult::success($this->currentYear);
  }

  private function getValidatedWithoutDiscount(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $withoutDiscountValue = $activityValues[ExportAktivitSloupce::BEZ_SLEV] ?? null;
    if ((string)$withoutDiscountValue === '') {
      return ImportStepResult::success($originalActivity && $originalActivity->bezSlevy()
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

  private function getValidatedUnisexCapacity(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_UNISEX] ?? null;
    if ((string)$capacityValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->getKapacitaUnisex()
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

  private function getValidatedMenCapacity(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_MUZI] ?? null;
    if ((string)$capacityValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->getKapacitaMuzu()
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

  private function getValidatedWomenCapacity(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_ZENY] ?? null;
    if ((string)$capacityValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->getKapacitaZen()
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

  private function getValidatedPrice(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
    if ((string)$priceValue === '') {
      return ImportStepResult::success($originalActivity
        ? $originalActivity->cenaZaklad()
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

  private function getValidatedLocationId(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $locationValue = $activityValues[ExportAktivitSloupce::MISTNOST] ?? null;
    if (!$locationValue) {
      if ($originalActivity) {
        return ImportStepResult::success($originalActivity->lokaceId());
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

  private function getValidatedStart(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $startValue = $activityValues[ExportAktivitSloupce::ZACATEK] ?? null;
    if (!$startValue) {
      if (!$originalActivity) {
        return ImportStepResult::success(null);
      }
      $activityStart = $originalActivity->zacatek();
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

  private function getValidatedEnd(array $activityValues, ?\Aktivita $originalActivity): ImportStepResult {
    $activityEndValue = $activityValues[ExportAktivitSloupce::KONEC] ?? null;
    if (!$activityEndValue) {
      if (!$originalActivity) {
        return ImportStepResult::success(null);
      }
      $activityEnd = $originalActivity->konec();
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

  private function getInstanceIdByUrl(string $url, int $programLineId): ?int {
    return \Aktivita::idInstancePodleUrl($url, $this->currentYear, $programLineId);
  }

  private function toUrl(string $value): string {
    $sanitized = strtolower(odstranDiakritiku($value));
    return preg_replace('~\W+~', '-', $sanitized);
  }

  private function getValidatedActivityName(array $activityValues, string $activityUrl, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ImportStepResult {
    $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
    if (!$activityNameValue) {
      return $originalActivity
        ? ImportStepResult::success($originalActivity->nazev())
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
