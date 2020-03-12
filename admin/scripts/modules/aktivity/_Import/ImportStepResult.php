<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ImportStepResult
{
  /**
   * @var mixed
   */
  private $success;
  /**
   * @var array
   */
  private $warnings;
  /**
   * @var string
   */
  private $error;

  public static function error(string $error): ImportStepResult {
    return new static(false, [], $error);
  }

  public static function success($success): ImportStepResult {
    return new static($success, [], '');
  }

  public static function successWithWarnings($success, array $warnings): ImportStepResult {
    return new static($success, $warnings, '');
  }

  private function __construct($success, array $warnings, string $error) {
    $this->success = $success;
    $this->warnings = $warnings;
    $this->error = $error;
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

  public function getSuccess() {
    return $this->success;
  }

  public function getWarnings(): array {
    return $this->warnings;
  }

  public function getError(): string {
    return $this->error;
  }
}
