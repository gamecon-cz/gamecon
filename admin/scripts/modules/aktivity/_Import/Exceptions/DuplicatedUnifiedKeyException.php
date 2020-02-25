<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Exceptions;

use Throwable;

class DuplicatedUnifiedKeyException extends ImportAktivitException
{
  private $duplicatedKey;

  public function __construct(string $message, string $duplicatedKey, int $code = 0, Throwable $previous = null) {
    parent::__construct($message, $code, $previous);
    $this->duplicatedKey = $duplicatedKey;
  }

  public function getDuplicatedKey(): string {
    return $this->duplicatedKey;
  }

}
