<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ImportStepResult
{
    /**
     * @var mixed
     */
    private $success;
    /**
     * @var string[]
     */
    private $warnings;
    /**
     * @var string[]
     */
    private $errorLikeWarnings;
    /**
     * @var string
     */
    private $error;
    /**
     * @var string|null
     */
    private $lastActivityDescription;

    public static function error(string $error): ImportStepResult {
        return new static(false, [], [], $error);
    }

    public static function success($success): ImportStepResult {
        return new static($success, [], [], '');
    }

    public static function successWithWarnings($success, array $warnings, array $errorLikeWarnings = []): ImportStepResult {
        return new static($success, $warnings, $errorLikeWarnings, '');
    }

    public static function successWithErrorLikeWarnings($success, array $errorLikeWarnings): ImportStepResult {
        return new static($success, [], $errorLikeWarnings, '');
    }

    /**
     * @param array|ImportStepResult[] $importStepsResults
     * @return array
     */
    public static function collectWarningsFromSteps(array $importStepsResults): array {
        $warnings          = [];
        $errorLikeWarnings = [];
        foreach ($importStepsResults as $importStepResult) {
            foreach ($importStepResult->getWarnings() as $warning) {
                $warnings[] = $warning;
            }
            foreach ($importStepResult->getErrorLikeWarnings() as $errorLikeWarning) {
                $errorLikeWarnings[] = $errorLikeWarning;
            }
        }
        return [
            'warnings'          => $warnings,
            'errorLikeWarnings' => $errorLikeWarnings,
        ];
    }

    private function __construct($success, array $warnings, array $errorLikeWarnings, string $error) {
        $this->success           = $success;
        $this->warnings          = $warnings;
        $this->errorLikeWarnings = $errorLikeWarnings;
        $this->error             = $error;
    }

    public function setLastActivityDescription(string $lastActivityDescription): ImportStepResult {
        $this->lastActivityDescription = $lastActivityDescription;
        return $this;
    }

    public function getLastActivityDescription(): ?string {
        return $this->lastActivityDescription;
    }

    public function isSuccess(): bool {
        return $this->success !== false;
    }

    public function isError(): bool {
        return $this->error !== '';
    }

    public function hasWarnings(): bool {
        return count($this->warnings) > 0;
    }

    public function hasErrorLikeWarnings(): bool {
        return count($this->errorLikeWarnings) > 0;
    }

    public function hasError(): bool {
        return $this->isError();
    }

    public function getSuccess() {
        return $this->success;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array {
        return $this->warnings;
    }

    /**
     * @return string[]
     */
    public function getErrorLikeWarnings(): array {
        return $this->errorLikeWarnings;
    }

    public function getError(): string {
        return $this->error;
    }
}
