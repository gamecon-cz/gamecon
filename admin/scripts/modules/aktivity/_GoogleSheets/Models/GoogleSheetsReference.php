<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

class GoogleSheetsReference
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
  private $spreadsheetId;
  /**
   * @var string
   */
  private $originalTitle;
  /**
   * @var string
   */
  private $createdAt;

  public function __construct(int $id, int $userId, string $spreadsheetId, string $originalTitle, string $createdAt) {
    $this->id = $id;
    $this->userId = $userId;
    $this->spreadsheetId = $spreadsheetId;
    $this->originalTitle = $originalTitle;
    $this->createdAt = $createdAt;
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
  public function getSpreadsheetId(): string {
    return $this->spreadsheetId;
  }

  /**
   * @return string
   */
  public function getOriginalTitle(): string {
    return $this->originalTitle;
  }

  /**
   * @return string
   */
  public function getCreatedAt(): string {
    return $this->createdAt;
  }

}
