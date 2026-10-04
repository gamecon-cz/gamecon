<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Service\RestrictedProductRules;
use App\Service\UserPermissions;
use Gamecon\Pravo;
use PHPUnit\Framework\TestCase;

class RestrictedProductRulesTest extends TestCase
{
    private function product(?ProductTagCode $tag): Product
    {
        $product = new Product();
        if ($tag !== null) {
            $productTag = new ProductTag();
            $productTag->setCode($tag->value);
            $product->addTag($productTag);
        }

        return $product;
    }

    public function testUnrestrictedProductNeverAsksForAPermission(): void
    {
        $permissions = $this->createMock(UserPermissions::class);
        $permissions->expects(self::never())->method('has');

        self::assertTrue(
            (new RestrictedProductRules($permissions))->mayOrder($this->product(ProductTagCode::TRICKO), new User(), 2026),
        );
    }

    public function testRestrictedProductNeedsItsOwnPermission(): void
    {
        $customer = new User();
        $permissions = $this->createMock(UserPermissions::class);
        $permissions->method('has')
            ->with($customer, Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA, 2026)
            ->willReturn(false);

        self::assertFalse(
            (new RestrictedProductRules($permissions))->mayOrder($this->product(ProductTagCode::TRICKO_CERVENE), $customer, 2026),
        );
    }

    public function testRestrictedProductIsOrderableWithThePermission(): void
    {
        $permissions = $this->createMock(UserPermissions::class);
        $permissions->method('has')->willReturn(true);

        self::assertTrue(
            (new RestrictedProductRules($permissions))->mayOrder($this->product(ProductTagCode::TRICKO_MODRE), new User(), 2026),
        );
    }
}
