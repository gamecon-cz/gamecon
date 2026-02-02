<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * ActivityMainLocationListener - automatically sets the main location from the first location
 *
 * Business rule: If Activity has no main location set, automatically set it to the first
 * location from the locations collection. This ensures every Activity with locations
 * has a sensible default main location.
 *
 * Triggers:
 * - prePersist: Before inserting new Activity
 * - preUpdate: Before updating existing Activity
 *
 * Logic:
 * - Only acts when the mainLocation is null (never overrides explicit choice)
 * - Sets mainLocation to first location from locations collection
 * - If locations are empty, the mainLocation remains null
 */
#[AsEntityListener(event: Events::prePersist, entity: Activity::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Activity::class)]
class ActivityMainLocationListener
{
    /**
     * @noinspection PhpUnused
     */
    public function prePersist(Activity $activity): void
    {
        $this->setMainLocationIfNeeded($activity);
    }

    public function preUpdate(Activity $activity): void
    {
        $this->setMainLocationIfNeeded($activity);
    }

    private function setMainLocationIfNeeded(Activity $activity): void
    {
        // Don't override explicitly set main location
        if ($activity->getMainLocation() !== null) {
            return;
        }

        $locations = $activity->getLocations();
        $firstLocation = $locations->first();
        if (! $firstLocation) {
            return;
        }
        $activity->setMainLocation($firstLocation);
    }
}
