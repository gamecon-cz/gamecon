<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;

class ImportModelsFetcher
{

  public static function fetchLocation(int $locationId): \Lokace {
    static $locations = [];
    if (!array_key_exists($locationId, $locations)) {
      $location = \Lokace::zId($locationId);
      if (!$location) {
        throw new ImportAktivitException("Location with ID '$locationId' does not exist");
      }
      $locations[$locationId] = $location;
    }
    return $locations[$locationId];
  }

  public static function fetchUser(int $userId): \Uzivatel {
    static $users = [];
    if (!array_key_exists($userId, $users)) {
      $user = \Uzivatel::zId($userId);
      if (!$user) {
        throw new ImportAktivitException("User with ID '$userId' does not exist");
      }
    }
    return $users[$userId];
  }

  public static function fetchActivity(int $activityId): \Aktivita {
    static $activities = [];
    if (!array_key_exists($activityId, $activities)) {
      $activity = \Aktivita::zId($activityId);
      if (!$activity) {
        throw new ImportAktivitException("Activity with ID '$activityId' does not exist");
      }
    }
    return $activities[$activityId];
  }

}
