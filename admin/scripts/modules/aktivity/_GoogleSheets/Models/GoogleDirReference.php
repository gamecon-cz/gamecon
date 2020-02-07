<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

class GoogleDirReference
{
  /**
   * @var int
   */
  private $id;
  /**
   * @var int
   */
  private $userId;
  /**
   * @var string
   */
  private $googleDirId;
  /**
   * @var string
   */
  private $originalDirName;
  /**
   * @var string
   */
  private $tag;

  public function __construct(int $id, int $userId, string $googleDirId, string $originalDirName, string $tag) {
    $this->id = $id;
    $this->userId = $userId;
    $this->googleDirId = $googleDirId;
    $this->originalDirName = $originalDirName;
    $this->tag = $tag;
  }

  /**
   * @return int
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * @return int
   */
  public function getUserId(): int {
    return $this->userId;
  }

  /**
   * @return string
   */
  public function getGoogleDirId(): string {
    return $this->googleDirId;
  }

  /**
   * @return string
   */
  public function getOriginalDirName(): string {
    return $this->originalDirName;
  }

  /**
   * @return string
   */
  public function getTag(): string {
    return $this->tag;
  }

}
