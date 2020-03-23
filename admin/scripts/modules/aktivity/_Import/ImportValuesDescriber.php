<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;

class ImportValuesDescriber
{
  /**
   * @var string
   */
  private $editActivityUrlSkeleton;

  public function __construct(string $editActivityUrlSkeleton) {
    $this->editActivityUrlSkeleton = $editActivityUrlSkeleton;
  }

  public function describeActivityById(int $activityId): string {
    $activity = ImportModelsFetcher::fetchActivity($activityId);
    return $this->describeActivity($activity);
  }

  public function describeActivity(\Aktivita $activity): string {
    return $this->getLinkToActivity($activity);
  }

  private function getLinkToActivity(\Aktivita $activity): string {
    return $this->createLinkToActivity($activity->id(), $this->describeActivityByInputValues([], $activity));
  }

  public function describeActivityByInputValues(array $activityValues, ?\Aktivita $originalActivity): string {
    return $this->describeActivityByValues(
      empty($activityValues[ExportAktivitSloupce::ID_AKTIVITY])
        ? null
        : (int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY],
      $activityValues[ExportAktivitSloupce::NAZEV] ?? null,
      $activityValues[ExportAktivitSloupce::URL] ?? null,
      $activityValues[ExportAktivitSloupce::KRATKA_ANOTACE] ?? null,
      $originalActivity
    );
  }

  public function describeActivityBySqlMappedValues(array $sqlMappedValues, ?\Aktivita $originalActivity): string {
    return $this->describeActivityByValues(
      $sqlMappedValues[AktivitaSqlSloupce::ID_AKCE] ?? null,
      $sqlMappedValues[AktivitaSqlSloupce::NAZEV_AKCE] ?? null,
      $sqlMappedValues[AktivitaSqlSloupce::URL_AKCE] ?? null,
      $sqlMappedValues[AktivitaSqlSloupce::POPIS_KRATKY] ?? null,
      $originalActivity
    );
  }

  public function describeActivityByValues(?int $id, ?string $nazev, ?string $url, ?string $kratkaAnotace, ?\Aktivita $originalActivity): string {
    if (!$id && $originalActivity) {
      $id = $originalActivity->id();
    }
    if (!$nazev && $originalActivity) {
      $nazev = $originalActivity->nazev();
    }
    if ($id) {
      $id = (int)$id;
    }
    if ($nazev) {
      $nazev = (string)$nazev;
    }
    if ($nazev && $id) {
      return sprintf("'%s' (%d)", $this->createLinkToActivity($id, $nazev), $id);
    }
    if (!$url && $originalActivity) {
      $url = $originalActivity->urlId();
    }
    if ($nazev && $url) {
      return "'$nazev' s URL '$url'";
    }
    if ($nazev) {
      return "'nazev'";
    }
    if (!$kratkaAnotace && $originalActivity) {
      $kratkaAnotace = $originalActivity->kratkyPopis();
    }
    return $kratkaAnotace
      ? "(bez názvu) '$kratkaAnotace'"
      : '(bez názvu)';
  }

  private function createLinkToActivity(int $id, string $name): string {
    return <<<HTML
<a target="_blank" href="{$this->editActivityUrlSkeleton}{$id}">{$name}</a>
HTML
      ;
  }

  public function describeLocationById(int $locationId): string {
    $location = ImportModelsFetcher::fetchLocation($locationId);
    return sprintf('%s (%s)', $location->nazev(), $location->id());
  }

  public function describeUserById(int $userId): string {
    $user = ImportModelsFetcher::fetchUser($userId);
    return $this->describeUser($user);
  }

  public function describeUser(\Uzivatel $user): string {
    return sprintf('%s (%s)', $user->jmenoNick(), $user->id());
  }
}
