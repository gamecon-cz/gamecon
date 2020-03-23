<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Vyjimkovac\Logovac;

class ActivityImporter
{
  /**
   * @var ImportValuesDescriber
   */
  private $importValuesDescriber;
  /**
   * @var ImportValuesChecker
   */
  private $importAccessibilityChecker;
  /**
   * @var \DateTimeInterface
   */
  private $now;
  /**
   * @var int
   */
  private $currentYear;
  /**
   * @var Logovac
   */
  private $logovac;

  public function __construct(
    ImportValuesDescriber $importValuesDescriber,
    ImportValuesChecker $importAccessibilityChecker,
    \DateTimeInterface $now,
    int $currentYear,
    Logovac $logovac
  ) {
    $this->importValuesDescriber = $importValuesDescriber;
    $this->importAccessibilityChecker = $importAccessibilityChecker;
    $this->now = $now;
    $this->currentYear = $currentYear;
    $this->logovac = $logovac;
  }

  public function importActivity(
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

    $checkResults = [];

    $stateUsabilityResult = $this->importAccessibilityChecker->checkStateUsability($values, $originalActivity);
    if ($stateUsabilityResult->isError()) {
      return ImportStepResult::error($stateUsabilityResult->getError());
    }
    $values[AktivitaSqlSloupce::STAV] = $stateUsabilityResult->getSuccess();
    $checkResults[] = $stateUsabilityResult;
    unset($stateUsabilityResult);

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
    $availableStorytellerIds = $storytellersAccessibilityResult->getSuccess();
    $checkResults[] = $storytellersAccessibilityResult;
    unset($storytellersAccessibilityResult);

    $locationAccessibilityResult = $this->importAccessibilityChecker->checkLocationByAccessibility(
      $values[AktivitaSqlSloupce::LOKACE],
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $originalActivity,
      $values
    );
    if ($locationAccessibilityResult->isError()) {
      return ImportStepResult::error($locationAccessibilityResult->getError());
    }
    $checkResults[] = $locationAccessibilityResult;
    unset($locationAccessibilityResult);

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
    $checkResults[] = $savedActivityResult;
    unset($savedActivityResult);

    ['warnings' => $warnings, 'errorLikeWarnings' => $errorLikeWarnings] = ImportStepResult::collectWarningsFromSteps($checkResults);

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

  private function findParentActivityId(string $url, \Typ $singleProgramLine): ?int {
    return \Aktivita::idMozneHlavniAktivityPodleUrl($url, $this->currentYear, $singleProgramLine->id());
  }

  private function createInstanceForParentActivity(int $parentActivityId): \Aktivita {
    $parentActivity = ImportModelsFetcher::fetchActivity($parentActivityId);
    return $parentActivity->instancuj();
  }

}
