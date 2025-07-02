<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\ActivitiesImportException;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Lokace;

class ImportModelsFetcher
{

    public static function fetchLocation(int $locationId): Lokace {
        static $locations = [];
        if (!array_key_exists($locationId, $locations)) {
            $location = Lokace::zId($locationId);
            if (!$location) {
                throw new ActivitiesImportException("Location with ID '$locationId' does not exist");
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
                throw new ActivitiesImportException("User with ID '$userId' does not exist");
            }
            $users[$userId] = $user;
        }
        return $users[$userId];
    }

    public static function fetchActivity(int $activityId): Aktivita {
        $activity = Aktivita::zId($activityId);
        if (!$activity) {
            throw new ActivitiesImportException("Activity with ID '$activityId' does not exist");
        }
        return $activity;
    }

}
