<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Activity;
use App\Entity\Location;
use PHPUnit\Framework\TestCase;

class ActivityMainLocationTest extends TestCase
{
    private Activity $activity;

    protected function setUp(): void
    {
        $this->activity = new Activity();
    }

    public function testMainLocationCanBeSet(): void
    {
        $location = $this->createMock(Location::class);
        $location->method('getId')->willReturn(42);
        $location->method('getNazev')->willReturn('Hlavní sál');

        $this->activity->setMainLocation($location);

        $this->assertSame($location, $this->activity->getMainLocation());
    }

    public function testMainLocationCanBeNull(): void
    {
        $this->assertNull($this->activity->getMainLocation());

        $location = $this->createMock(Location::class);
        $this->activity->setMainLocation($location);
        $this->assertNotNull($this->activity->getMainLocation());

        $this->activity->setMainLocation(null);
        $this->assertNull($this->activity->getMainLocation());
    }

    public function testMainLocationIsIndependent(): void
    {
        $location1 = $this->createMock(Location::class);
        $location1->method('getId')->willReturn(1);

        $location2 = $this->createMock(Location::class);
        $location2->method('getId')->willReturn(2);

        $this->activity->setMainLocation($location1);
        $this->assertSame($location1, $this->activity->getMainLocation());

        $this->activity->setMainLocation($location2);
        $this->assertSame($location2, $this->activity->getMainLocation());
        $this->assertNotSame($location1, $this->activity->getMainLocation());
    }
}
