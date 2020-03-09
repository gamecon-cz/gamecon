<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ResultOfActivitiesImport
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
  private $errorMessages = [];

  public function __construct() {
  }

  public function incrementImportedCount(): int {
    $this->importedCount++;
    return $this->importedCount;
  }

  public function setProcessedFilename(string $processedFilename): ResultOfActivitiesImport {
    if ($this->processedFilename !== null && $this->processedFilename !== $processedFilename) {
      throw new \LogicException(sprintf('Processed filename is already set to %s and can not be changed to %s.', $this->processedFilename, $processedFilename));
    }
    if ($processedFilename === '') {
      throw new \LogicException('Processed filename has empty name.');
    }
    $this->processedFilename = $processedFilename;
    return $this;
  }

  public function addErrorMessage(string $errorMessage): ResultOfActivitiesImport {
    $this->errorMessages[] = $errorMessage;
    return $this;
  }

  public function addWarningMessage(string $warningMessage): ResultOfActivitiesImport {
    $this->warningMessages[] = $warningMessage;
    return $this;
  }

  public function addSuccessMessage(string $successMessage): ResultOfActivitiesImport {
    $this->successMessages[] = $successMessage;
    return $this;
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
    return $this->errorMessages;
  }
}
