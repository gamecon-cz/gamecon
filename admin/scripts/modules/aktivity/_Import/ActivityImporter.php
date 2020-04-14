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
   * @var ImportSqlMappedValuesChecker
   */
  private $importValuesChecker;
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
    ImportSqlMappedValuesChecker $importValuesChecker,
    \DateTimeInterface $now,
    int $currentYear,
    Logovac $logovac
  ) {
    $this->importValuesDescriber = $importValuesDescriber;
    $this->importValuesChecker = $importValuesChecker;
    $this->now = $now;
    $this->currentYear = $currentYear;
    $this->logovac = $logovac;
  }

  public function importActivity(
    array $sqlMappedValues,
    ?string $longAnnotation,
    array $storytellersIds,
    array $tagIds,
    \Typ $singleProgramLine,
    ?\Aktivita $originalActivity
  ): ImportStepResult {
    $checkBeforeSaveResult = $this->checkBeforeSave($sqlMappedValues, $storytellersIds, $singleProgramLine, $originalActivity);
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
          'message' => 'Upravena existující aktivita.',
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
            'Nahrána jako nová, %d. <strong>instance</strong> k hlavní aktivitě %s.',
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
        'message' => 'Nahrána jako nová aktivita.',
        'importedActivity' => $importedActivity,
      ],
      $warnings,
      $errorLikeWarnings
    );
  }

  private function checkBeforeSave(array $sqlMappedValues, array $storytellersIds, \Typ $singleProgramLine, ?\Aktivita $originalActivity): ImportStepResult {
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

    $durationResult = $this->importValuesChecker->checkDuration($sqlMappedValues, $originalActivity);
    if ($durationResult->isError()) {
      return ImportStepResult::error($durationResult->getError());
    }
    ['start' => $start, 'end' => $end] = $durationResult->getSuccess();
    $sqlMappedValues[AktivitaSqlSloupce::ZACATEK] = $start ?: null;
    $sqlMappedValues[AktivitaSqlSloupce::KONEC] = $end ?: null;
    $checkResults[] = $durationResult;
    unset($durationResult);

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

    $stateUsabilityResult = $this->importValuesChecker->checkStateUsability($sqlMappedValues);
    if ($stateUsabilityResult->isError()) {
      return ImportStepResult::error($stateUsabilityResult->getError());
    }
    $sqlMappedValues[AktivitaSqlSloupce::STAV] = $stateUsabilityResult->getSuccess();
    $checkResults[] = $stateUsabilityResult;
    unset($stateUsabilityResult);

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
      $originalActivity
    );
    if ($locationAccessibilityResult->isError()) {
      return ImportStepResult::error($locationAccessibilityResult->getError());
    }
    $checkResults[] = $locationAccessibilityResult;
    unset($locationAccessibilityResult);

    return ImportStepResult::success(['values' => $sqlMappedValues, 'availableStorytellerIds' => $availableStorytellerIds, 'checkResults' => $checkResults]);
  }

  private function saveActivity(
    array $sqlMappedValues,
    ?string $longAnnotation,
    array $storytellersIds,
    array $tagIds,
    \Typ $singleProgramLine
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
      return ImportStepResult::success($savedActivity);
    } catch (\Exception $exception) {
      $this->logovac->zaloguj($exception);
      return ImportStepResult::error(sprintf('Aktivitu se nepodařilo uložit: %s.', $exception->getMessage()));
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
