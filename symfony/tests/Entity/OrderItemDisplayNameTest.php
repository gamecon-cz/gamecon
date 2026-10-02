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
     * The size or night bought is the variant; the product is only its model, whose code the
     * cart snapshots for every size.
     */
    public function testCodeIsTheVariantsEvenBesideTheModelsSnapshot(): void
    {
        $model = (new Product())->setName('Tričko')->setCode('tricko_XXXL')->setCurrentPrice('250.00');
        $item = new OrderItem();
        $item->snapshotProduct($model, (new ProductVariant())->setName('S')->setCode('tricko_S')->setProduct($model));

        self::assertSame('tricko_S', $item->getDisplayCode());
    }

    public function testCodeWithoutSnapshotIsTheVariants(): void
    {
        $item = new OrderItem();
        $item->setProduct((new Product())->setCode('tricko_XXXL'));
        $item->setVariant((new ProductVariant())->setCode('tricko_S'));

        self::assertSame('tricko_S', $item->getDisplayCode());
    }

    public function testVariantMatchingOnlyPartOfAWordIsShown(): void
    {
        $item = new OrderItem();
        $item->setProductName('Tričko Sport');
        $item->setVariantName('S');

        self::assertSame('Tričko Sport — S', $item->getDisplayName());
    }
}
