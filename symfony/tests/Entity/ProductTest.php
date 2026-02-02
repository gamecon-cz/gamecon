<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        $this->product = new Product();
        $this->product->setName('Test Product');
        $this->product->setCode('TEST-001');
        $this->product->setCurrentPrice('100.00');
        $this->product->setState(1);
        $this->product->setDescription('Test description');
    }

    public function testProductCreation(): void
    {
        $this->assertNull($this->product->getId());
        $this->assertSame('Test Product', $this->product->getName());
        $this->assertSame('TEST-001', $this->product->getCode());
        $this->assertSame('100.00', $this->product->getCurrentPrice());
        $this->assertSame(1, $this->product->getState());
        $this->assertSame('Test description', $this->product->getDescription());
    }

    public function testTagManagement(): void
    {
        $this->assertFalse($this->product->hasTag('kostka'));

        $this->product->addTag('kostka');

        $this->assertTrue($this->product->hasTag('kostka'));
        $this->assertCount(1, $this->product->getTags());

        // Adding same tag again should not duplicate
        $this->product->addTag('kostka');
        $this->assertCount(1, $this->product->getTags());

        // Add another tag
        $this->product->addTag('predmet');
        $this->assertCount(2, $this->product->getTags());
        $this->assertTrue($this->product->hasTag('predmet'));

        // Remove tag
        $this->product->removeTag('kostka');
        $this->assertFalse($this->product->hasTag('kostka'));
        $this->assertTrue($this->product->hasTag('predmet'));
        $this->assertCount(1, $this->product->getTags());
    }

    public function testGetTagNames(): void
    {
        $this->product->addTag('kostka');
        $this->product->addTag('predmet');

        $tagNames = $this->product->getTagNames();

        $this->assertIsArray($tagNames);
        $this->assertCount(2, $tagNames);
        $this->assertContains('kostka', $tagNames);
        $this->assertContains('predmet', $tagNames);
    }

    public function testIsAccommodation(): void
    {
        $this->assertFalse($this->product->isAccommodation());

        $this->product->addTag('ubytovani');

        $this->assertTrue($this->product->isAccommodation());
    }

    public function testAmountFields(): void
    {
        $this->assertNull($this->product->getAmountOrganizers());
        $this->assertNull($this->product->getAmountParticipants());
        $this->assertFalse($this->product->hasSeparateOrganizerAmount());

        $this->product->setAmountOrganizers(10);
        $this->product->setAmountParticipants(50);

        $this->assertSame(10, $this->product->getAmountOrganizers());
        $this->assertSame(50, $this->product->getAmountParticipants());
        $this->assertTrue($this->product->hasSeparateOrganizerAmount());
    }

    public function testGetTotalAmount(): void
    {
        // Using producedQuantity
        $this->product->setProducedQuantity(100);
        $this->assertSame(100, $this->product->getTotalAmount());

        // Using separate amounts (overrides producedQuantity)
        $this->product->setAmountOrganizers(10);
        $this->product->setAmountParticipants(50);
        $this->assertSame(60, $this->product->getTotalAmount());

        // Only organizers amount set
        $this->product->setAmountParticipants(null);
        $this->assertSame(10, $this->product->getTotalAmount());
    }

    public function testIsAvailable(): void
    {
        // State 1 = VEŘEJNÝ (public)
        $this->product->setState(1);
        $this->assertTrue($this->product->isAvailable());

        // State 0 = MIMO (not available)
        $this->product->setState(0);
        $this->assertFalse($this->product->isAvailable());

        // Archived product
        $this->product->setState(1);
        $this->product->setArchivedAt(new \DateTimeImmutable());
        $this->assertFalse($this->product->isAvailable());

        // Expired availability
        $this->product->restore();
        $this->product->setAvailableUntil(new \DateTimeImmutable('-1 day'));
        $this->assertFalse($this->product->isAvailable());

        // Future availability
        $this->product->setAvailableUntil(new \DateTimeImmutable('+1 day'));
        $this->assertTrue($this->product->isAvailable());
    }

    public function testIsPublic(): void
    {
        $this->product->setState(1); // VEŘEJNÝ
        $this->assertTrue($this->product->isPublic());

        $this->product->setState(2); // PODPULTOVÝ
        $this->assertFalse($this->product->isPublic());

        $this->product->setState(1);
        $this->product->setArchivedAt (new \DateTimeImmutable);
        $this->assertFalse($this->product->isPublic());
    }

    public function testGetStateName(): void
    {
        $this->product->setState(0);
        $this->assertSame('Mimo', $this->product->getStateName());

        $this->product->setState(1);
        $this->assertSame('Veřejný', $this->product->getStateName());

        $this->product->setState(2);
        $this->assertSame('Podpultový', $this->product->getStateName());

        $this->product->setState(3);
        $this->assertSame('Pozastavený', $this->product->getStateName());
    }
}
