<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ActivitiesImportResult
{
  /**
   * @var int
   */
  private $importedCount = 0;
  /**
   * @var string|null
   */
  private $processedFilename;

  /**
   * @var string[]
   */
  private $successMessages = [];
  /**
   * @var string[]
   */
  private $warningMessages = [];
  /**
   * @var string[]
   */
  private $errorLikeWarningMessages = [];
  /**
   * @var string[]
   */
  private $errorMessages = [];

  public function incrementImportedCount(): int {
    $this->importedCount++;
    return $this->importedCount;
  }

  public function setProcessedFilename(string $processedFilename): ActivitiesImportResult {
    if ($this->processedFilename !== null && $this->processedFilename !== $processedFilename) {
      throw new \LogicException(sprintf('Processed filename is already set to %s and can not be changed to %s.', $this->processedFilename, $processedFilename));
    }
    if ($processedFilename === '') {
      throw new \LogicException('Processed filename has empty name.');
    }
    $this->processedFilename = $processedFilename;
    return $this;
  }

  public function addErrorMessage(string $errorMessage, ?string $activityGuid): ActivitiesImportResult {
    $this->errorMessages[] = $activityGuid !== null
      ? "{$activityGuid}: {$errorMessage}"
      : $errorMessage;
    return $this;
  }

  public function addWarningMessage(string $warningMessage, ?string $activityGuid): ActivitiesImportResult {
    $this->warningMessages[] = $activityGuid !== null
      ? "{$activityGuid}: {$warningMessage}"
      : $warningMessage;
    return $this;
  }

  public function addWarnings(ImportStepResult $importStepResult, ?string $activityGuid): ActivitiesImportResult {
    foreach ($importStepResult->getWarnings() as $warningMessage) {
      $this->addWarningMessage($warningMessage, $activityGuid);
    }
    return $this;
  }

  public function addErrorLikeWarnings(ImportStepResult $importStepResult, ?string $activityGuid): ActivitiesImportResult {
    foreach ($importStepResult->getErrorLikeWarnings() as $errorLikeWarningMessage) {
      $this->addErrorLikeWarningMessage($errorLikeWarningMessage, $activityGuid);
    }
    return $this;
  }

  public function addErrorLikeWarningMessage(string $errorLikeWarningMessage, ?string $activityGuid): ActivitiesImportResult {
    $this->errorLikeWarningMessages[] = $activityGuid !== null
      ? "{$activityGuid}: {$errorLikeWarningMessage}"
      : $errorLikeWarningMessage;
    return $this;
  }

  public function addSuccessMessage(string $successMessage): ActivitiesImportResult {
    $this->successMessages[] = $successMessage;
    return $this;
  }

  public function solveActivityDescription(string $activityGuid, string $activityFinalDescription) {
    foreach ($this->errorMessages as $index => $errorMessage) {
      $this->errorMessages[$index] = str_replace($activityGuid, $activityFinalDescription, $errorMessage);
    }
    foreach ($this->errorLikeWarningMessages as $index => $errorLikeWarningMessage) {
      $this->errorLikeWarningMessages[$index] = str_replace($activityGuid, $activityFinalDescription, $errorLikeWarningMessage);
    }
    foreach ($this->warningMessages as $index => $warningMessage) {
      $this->warningMessages[$index] = str_replace($activityGuid, $activityFinalDescription, $warningMessage);
    }
    foreach ($this->successMessages as $index => $successMessage) {
      $this->successMessages[$index] = str_replace($activityGuid, $activityFinalDescription, $successMessage);
    }
  }

  /**
   * @return int
   */
  public function getImportedCount(): int {
    return $this->importedCount;
  }

  /**
   * @return string|null
   */
  public function getProcessedFilename(): ?string {
    return $this->processedFilename;
  }

  /**
   * @return string[]
   */
  public function getSuccessMessages(): array {
    return $this->successMessages;
  }

  /**
   * @return string[]
   */
  public function getWarningMessages(): array {
    return $this->warningMessages;
  }

  /**
   * @return string[]
   */
  public function getErrorMessages(): array {
    return array_merge(
      $this->errorMessages,
      $this->errorLikeWarningMessages
    );
  }
}
