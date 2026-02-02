<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Activity;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests for ActivityMainLocationListener
 *
 * Verifies that the listener automatically sets main location from first location
 * when no main location is explicitly set.
 */
class ActivityMainLocationListenerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Test that listener sets main location on 'persist' when null and locations exist
     */
    public function testListenerSetsMainLocationOnPersistWhenNullAndLocationsExist(): void
    {
        // Create locations
        $location1 = new Location();
        $location1->setNazev('Location 1');
        $location1->setRok(2026);

        $location2 = new Location();
        $location2->setNazev('Location 2');
        $location2->setRok(2026);

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with locations but no main location
        $activity = new Activity();
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
        $location1 = new Location();
        $location1->setNazev('Initial Location');
        $location1->setRok(2026);

        $location2 = new Location();
        $location2->setNazev('New Location');
        $location2->setRok(2026);

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with main location set
        $activity = new Activity();
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
        $location1 = new Location();
        $location1->setNazev('First Location');
        $location1->setRok(2026);

        $location2 = new Location();
        $location2->setNazev('Main Location');
        $location2->setRok(2026);

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with explicit main location (second location)
        $activity = new Activity();
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
        $activity = new Activity();

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
        $location1 = new Location();
        $location1->setNazev('First');
        $location1->setRok(2026);

        $location2 = new Location();
        $location2->setNazev('Second');
        $location2->setRok(2026);

        $location3 = new Location();
        $location3->setNazev('Third');
        $location3->setRok(2026);

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->persist($location3);
        $this->entityManager->flush();

        // Create activity and add locations in specific order
        $activity = new Activity();
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
        $location1 = new Location();
        $location1->setNazev('To Remove');
        $location1->setRok(2026);

        $location2 = new Location();
        $location2->setNazev('To Keep');
        $location2->setRok(2026);

        $this->entityManager->persist($location1);
        $this->entityManager->persist($location2);
        $this->entityManager->flush();

        // Create activity with two locations, second as main
        $activity = new Activity();
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
        $location = new Location();
        $location->setNazev('Test Location');
        $location->setRok(2026);

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        // Create activity with location
        $activity = new Activity();
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
        $initialLocation = new Location();
        $initialLocation->setNazev('Initial');
        $initialLocation->setRok(2026);

        $this->entityManager->persist($initialLocation);
        $this->entityManager->flush();

        // Create activity with initial location
        $activity = new Activity();
        $activity->addLocation($initialLocation);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        // Verify main location is set
        $this->assertSame($initialLocation, $activity->getMainLocation());

        // Add new location
        $newLocation = new Location();
        $newLocation->setNazev('New');
        $newLocation->setRok(2026);

        $this->entityManager->persist($newLocation);
        $this->entityManager->flush();

        $activity->addLocation($newLocation);
        $this->entityManager->flush();

        // Main location should still be the initial one (not null, so listener doesn't change it)
        $this->assertSame($initialLocation, $activity->getMainLocation());
        $this->assertNotSame($newLocation, $activity->getMainLocation());
    }
}
