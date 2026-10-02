<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
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

    /**
     * Without a code snapshot, the size or night bought is the variant; the product is only its model.
     */
    public function testCodeWithoutSnapshotIsTheVariants(): void
    {
        $item = new OrderItem();
        $item->setProduct((new Product())->setCode('tricko_S'));
        $item->setVariant((new ProductVariant())->setCode('tricko_XL'));

        self::assertSame('tricko_XL', $item->getDisplayCode());
    }

    public function testVariantMatchingOnlyPartOfAWordIsShown(): void
    {
        $item = new OrderItem();
        $item->setProductName('Tričko Sport');
        $item->setVariantName('S');

        self::assertSame('Tričko Sport — S', $item->getDisplayName());
    }
}
