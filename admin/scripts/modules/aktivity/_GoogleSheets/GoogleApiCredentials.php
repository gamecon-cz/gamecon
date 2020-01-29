<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

class GoogleApiCredentials
{
  /**
   * @var array
   */
  private $values;

  public function __construct(array $values) {
    $this->values = $values;
  }

  public function getValues(): array {
    return $this->values;
  }
}
