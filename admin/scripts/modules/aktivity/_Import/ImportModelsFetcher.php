<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;

class ImportModelsFetcher
{

  public static function fetchLocation(int $locationId): \Lokace {
    $location = \Lokace::zId($locationId);
    if (!$location) {
      throw new ImportAktivitException("Location with ID '$locationId' does not exist");
    }
    return $location;
  }

  public static function fetchUser(int $userId): \Uzivatel {
    $user = \Uzivatel::zId($userId);
    if (!$user) {
      throw new ImportAktivitException("User with ID '$userId' does not exist");
    }
    return $user;
  }

  public static function fetchActivity(int $activityId): \Aktivita {
    $activity = \Aktivita::zId($activityId);
    if (!$activity) {
      throw new ImportAktivitException("Activity with ID '$activityId' does not exist");
    }
    return $activity;
  }

}
