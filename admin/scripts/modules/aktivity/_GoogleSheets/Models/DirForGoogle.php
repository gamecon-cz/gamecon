<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

class DirForGoogle
{
  /** @var string */
  private $originalDir;

  /** @var string */
  private $sanitizedDir;

  public function __construct(string $dir) {
    $this->originalDir = $dir;
    $this->sanitizedDir = rtrim(trim($dir), '/');
  }

  /**
   * @return string[]
   */
  public function getHierarchy(): array {
    return explode('/', ltrim($this->sanitizedDir, '/'));
  }

  public function __toString() {
    return $this->originalDir;
  }

}
