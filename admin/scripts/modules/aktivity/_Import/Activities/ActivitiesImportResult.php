<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

class ActivitiesImportResult
{
    private const GUID_FOR_NO_ACTIVITY = '';
    /**
     * @var int
     */
    private $importedCount = 0;
    /**
     * @var string|null
     */
    private $processedFilename;

    /**
     * @var string[][]
     */
    private $successMessages = [];
    /**
     * @var string[][]
     */
    private $warningMessages = [];
    /**
     * @var string[][]
     */
    private $errorLikeWarningMessages = [];
    /**
     * @var string[][]
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
        $this->errorMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $errorMessage;
        return $this;
    }

    public function addWarningMessage(string $warningMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->warningMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $warningMessage;
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
        $this->errorLikeWarningMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $errorLikeWarningMessage;
        return $this;
    }

    public function addSuccessMessage(string $successMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->successMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $successMessage;
        return $this;
    }

    public function solveActivityDescription(string $activityGuidToSolve, string $activityFinalDescription) {
        $this->errorMessages = $this->addActivityDescription($this->errorMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->errorLikeWarningMessages = $this->addActivityDescription($this->errorLikeWarningMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->warningMessages = $this->addActivityDescription($this->warningMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->successMessages = $this->addActivityDescription($this->successMessages, $activityGuidToSolve, $activityFinalDescription);
    }

    private function addActivityDescription(array $messagesByGuid, string $guidToSolve, string $activityFinalDescription): array {
        if (!isset($messagesByGuid[$guidToSolve])) {
            return $messagesByGuid;
        }
        $uniqueActivityFinalDescription = $activityFinalDescription;
        $attempt = 1;
        // happens on activities without known ID, but with same name and URL
        while (isset($messagesByGuid[$uniqueActivityFinalDescription])) {
            $attempt++;
            $uniqueActivityFinalDescription = $activityFinalDescription . '~' . $attempt;
        }
        $messagesByGuid[$uniqueActivityFinalDescription] = $messagesByGuid[$guidToSolve];
        unset($messagesByGuid[$guidToSolve]); // messages by final description in fact
        return $messagesByGuid;
    }

    public function getImportedCount(): int {
        return $this->importedCount;
    }

    public function getProcessedFilename(): ?string {
        return $this->processedFilename;
    }

    /**
     * @return string[][] Like [['Aktivita 123' => ['něco', 'něco jiného']]]
     */
    public function getSuccessMessages(): array {
        return $this->successMessages;
    }

    /**
     * @param array $exclude Activity IDs to exclude as a key
     * @return string[][] Like [['Aktivita 123' => ['něco', 'něco jiného']]]
     */
    public function getSuccessMessagesExceptFor(array $exclude): array {
        unset($exclude[self::GUID_FOR_NO_ACTIVITY]);
        return array_diff_key($this->successMessages, $exclude);
    }

    /**
     * @return string[][] Like [['Aktivita 123' => ['něco', 'něco jiného']]]
     */
    public function getWarningMessages(): array {
        return $this->warningMessages;
    }

    /**
     * Without messages about errored activities
     * @return string[][] Like [['Aktivita 123' => ['něco', 'něco jiného']]]
     */
    public function getErrorLikeAndWarningMessagesExceptErrored(): array {
        $errorLikeAndWarnings = array_merge_recursive(
            $this->errorLikeWarningMessages,
            $this->warningMessages
        );
        $exceptGuidsAsKeys = $this->errorMessages;
        unset($exceptGuidsAsKeys[self::GUID_FOR_NO_ACTIVITY]);
        return array_diff_key(
            $errorLikeAndWarnings,
            $exceptGuidsAsKeys
        );
    }

    /**
     * @return string[][] Like [['Aktivita 123' => ['něco', 'něco jiného']]]
     */
    public function getErrorMessages(): array {
        return $this->errorMessages;
    }

    public function wasImportCanceled(): bool {
        foreach ($this->getErrorMessages() as $activityGuid => $singleActivityErrorMessages) {
            if ($activityGuid !== self::GUID_FOR_NO_ACTIVITY) {
                return false;
            }
        }
        return true;
    }

    public function wasProblemWith(string $activityGuid): bool {
        return !empty($this->errorMessages[$activityGuid])
            || !empty($this->errorLikeWarningMessages[$activityGuid])
            || !empty($this->warningMessages[$activityGuid]);
    }
}
