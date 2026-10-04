<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Activity;
use App\Entity\ActivityStatus;
use App\Entity\ActivityType;
use App\Entity\Location;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests for ActivityMainLocationListener
 *
 * Verifies that the listener automatically sets main location from first location
 * when no main location is explicitly set.
 */
class ActivityMainLocationListenerTest extends AbstractDatabaseKernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->entityManager();
    }

    private function createLocation(string $name): Location
    {
        $location = new Location();
        $location->setNazev($name);
        $location->setRok(2026);
        // dvere, poznamka and poradi are NOT NULL without a DB default.
        $location->setDvere('');
        $location->setPoznamka('');
        $location->setPoradi(0);

        return $location;
    }

    /**
     * akce_seznam has no DB defaults, so every NOT NULL column must be set explicitly
     * even though this test only cares about the location relation.
     */
    private function createActivity(): Activity
    {
        $activity = new Activity();
        $activity->setType($this->entityManager->getReference(ActivityType::class, 1));
        $activity->setStatus($this->entityManager->getReference(ActivityStatus::class, 1));
        $activity->setNazevAkce('Test Activity');
        $activity->setKapacita(0);
        $activity->setKapacitaF(0);
        $activity->setKapacitaM(0);
        $activity->setCena(0);
        $activity->setBezSlevy(false);
        $activity->setNedavaBonus(false);
        $activity->setRok(2026);
        $activity->setTeamova(false);
        $activity->setTymSmazatPoExpiraci(false);
        $activity->setDescription('');
        $activity->setShortDescription('');
        $activity->setVybaveni('');
        $activity->setProbehlaKorekce(false);

        return $activity;
    }

    /**
     * Test that listener sets main location on 'persist' when null and locations exist
     */
    public function testListenerSetsMainLocationOnPersistWhenNullAndLocationsExist(): void
    {
        // Create locations
        $location1 = $this->createLocation('Location 1');

        $location2 = $this->createLocation('Location 2');

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with locations but no main location
        $activity = $this->createActivity();
        $activity->addLocation($location1);
        $activity->addLocation($location2);

        // Verify main location is null before persist
        $this->assertNull($activity->getMainLocation());

        // Persist activity - this should trigger the listener
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location is set to first location
        $this->assertNotNull($activity->getMainLocation());
        $this->assertSame($location1, $activity->getMainLocation());
    }

    /**
     * Test that listener sets main location on update when null and locations exist
     */
    public function testListenerSetsMainLocationOnUpdateWhenNullAndLocationsExist(): void
    {
        // Create location
        $location1 = $this->createLocation('Initial Location');

        $location2 = $this->createLocation('New Location');

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with main location set
        $activity = $this->createActivity();
        $activity->addLocation($location1);
        $activity->setMainLocation($location1);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Clear main location and add new location
        $activity->setMainLocation(null);
        $activity->addLocation($location2);

        // Update entity - this should trigger the listener
        $this->entityManager->flush();

        // Verify main location is set to first location (location1, since it was added first)
        $this->assertNotNull($activity->getMainLocation());
        $this->assertSame($location1, $activity->getMainLocation());
    }

    /**
     * Test that listener does not override existing main location
     */
    public function testListenerDoesNotOverrideExistingMainLocation(): void
    {
        // Create locations
        $location1 = $this->createLocation('First Location');

        $location2 = $this->createLocation('Main Location');

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with explicit main location (second location)
        $activity = $this->createActivity();
        $activity->addLocation($location1);
        $activity->addLocation($location2);
        $activity->setMainLocation($location2); // Explicitly set to second location

        // Persist activity
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location is still the explicitly set one (location2), not first (location1)
        $this->assertNotNull($activity->getMainLocation());
        $this->assertSame($location2, $activity->getMainLocation());
        $this->assertNotSame($location1, $activity->getMainLocation());
    }

    /**
     * Test that listener handles empty locations gracefully
     */
    public function testListenerHandlesEmptyLocationsGracefully(): void
    {
        // Create activity with no locations
        $activity = $this->createActivity();

        // Verify no locations and no main location
        $this->assertCount(0, $activity->getLocations());
        $this->assertNull($activity->getMainLocation());

        // Persist activity - listener should not throw error
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location remains null
        $this->assertNull($activity->getMainLocation());
    }

    /**
     * Test that listener sets first location when multiple locations exist
     */
    public function testListenerSetsFirstLocationWhenMultipleLocationsExist(): void
    {
        // Create three locations
        $location1 = $this->createLocation('First');

        $location2 = $this->createLocation('Second');

        $location3 = $this->createLocation('Third');

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->persist($location3);
        $this->entityManager->flush();

        // Create activity and add locations in specific order
        $activity = $this->createActivity();
        $activity->addLocation($location1);
        $activity->addLocation($location2);
        $activity->addLocation($location3);

        // Persist activity
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location is the first one
        $this->assertNotNull($activity->getMainLocation());
        $this->assertSame($location1, $activity->getMainLocation());
    }

    /**
     * Test that listener works after removing main location from collection
     */
    public function testListenerWorksAfterRemovingMainLocationFromCollection(): void
    {
        // Create two locations
        $location1 = $this->createLocation('To Remove');

        $location2 = $this->createLocation('To Keep');

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with two locations, second as main
        $activity = $this->createActivity();
        $activity->addLocation($location1);
        $activity->addLocation($location2);
        $activity->setMainLocation($location2);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Remove first location and clear main location
        $activity->removeLocation($location1);
        $activity->setMainLocation(null);

        // Update entity
        $this->entityManager->flush();

        // Verify main location is set to the remaining location
        $this->assertNotNull($activity->getMainLocation());
        $this->assertSame($location2, $activity->getMainLocation());
        $this->assertCount(1, $activity->getLocations());
    }

    /**
     * Test that listener respects null main location when explicitly set to null
     */
    public function testListenerRespectsExplicitNullMainLocation(): void
    {
        // Create location
        $location = $this->createLocation('Test Location');

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        // Create activity with location
        $activity = $this->createActivity();
        $activity->addLocation($location);

        // Persist - main location should be auto-set
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->assertSame($location, $activity->getMainLocation());

        // Now explicitly set to null and update
        $activity->setMainLocation(null);
        $this->entityManager->flush();

        // After update, listener should re-set it to first location
        // (because listener runs on preUpdate and sees mainLocation is null)
        $this->assertSame($location, $activity->getMainLocation());
    }

    /**
     * Test that adding a new first location doesn't change existing main location
     */
    public function testAddingNewLocationDoesNotChangeExistingMainLocation(): void
    {
        // Create initial location
        $initialLocation = $this->createLocation('Initial');

        $this->entityManager->persist($initialLocation);
        $this->entityManager->flush();

        // Create activity with initial location
        $activity = $this->createActivity();
        $activity->addLocation($initialLocation);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location is set
        $this->assertSame($initialLocation, $activity->getMainLocation());

        // Add new location
        $newLocation = $this->createLocation('New');

        $this->entityManager->persist($newLocation);
        $this->entityManager->flush();

        $activity->addLocation($newLocation);
        $this->entityManager->flush();

        // Main location should still be the initial one (not null, so listener doesn't change it)
        $this->assertSame($initialLocation, $activity->getMainLocation());
        $this->assertNotSame($newLocation, $activity->getMainLocation());
    }
}
