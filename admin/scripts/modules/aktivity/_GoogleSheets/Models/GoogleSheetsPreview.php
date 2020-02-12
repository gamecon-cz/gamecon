<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

class GoogleSheetsPreview
{
  /**
   * @var string
   */
  private $id;
  /**
   * @var string
   */
  private $name;
  /**
   * @var string
   */
  private $url;
  /**
   * @var \DateTimeCz
   */
  private $createdAt;
  /**
   * @var \DateTimeCz
   */
  private $modifiedAt;

  public function __construct(string $id, string $name, string $url, string $createdAt, string $modifiedAt) {
    $this->id = $id;
    $this->name = $name;
    $this->url = $url;
    $this->createdAt = new \DateTimeCz($createdAt);
    $this->modifiedAt = new \DateTimeCz($modifiedAt);
  }

  /**
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getUrl(): string {
    return $this->url;
  }

  public function getCreatedAt(): \DateTimeCz {
    return $this->createdAt;
  }

  public function getModifiedAt(): \DateTimeCz {
    return $this->modifiedAt;
  }

}
