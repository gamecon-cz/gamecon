<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\OrderItem;
use PHPUnit\Framework\TestCase;

class OrderItemDisplayNameTest extends TestCase
{
    public function testVariantThatAddsSomethingIsShown(): void
    {
        $item = new OrderItem();
        $item->setProductName('Tričko');
        $item->setVariantName('XL');

        self::assertSame('Tričko — XL', $item->getDisplayName());
    }

    /**
     * A product sold in one variant names it after itself; repeating it reads as a typo in the
     * cart and in the cancelled-purchase reports.
     */
    public function testVariantRepeatingTheNameIsNotShownTwice(): void
    {
        $item = new OrderItem();
        $item->setProductName('Placka');
        $item->setVariantName('Placka');

        self::assertSame('Placka', $item->getDisplayName());
    }

    public function testVariantAlreadyNamedInTheProductIsNotRepeated(): void
    {
        $item = new OrderItem();
        $item->setProductName('Postel na pokoji pátek');
        $item->setVariantName('pátek');

        self::assertSame('Postel na pokoji pátek', $item->getDisplayName());
    }

    public function testVariantMatchingOnlyPartOfAWordIsShown(): void
    {
        $item = new OrderItem();
        $item->setProductName('Tričko Sport');
        $item->setVariantName('S');

        self::assertSame('Tričko Sport — S', $item->getDisplayName());
    }
}
