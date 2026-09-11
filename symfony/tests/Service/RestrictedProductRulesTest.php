<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Enum\ProductTagCode;
use App\Service\RestrictedProductRules;
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
        $customer = $this->createMock(\Uzivatel::class);
        $customer->expects(self::never())->method('maPravo');

        self::assertTrue(
            (new RestrictedProductRules())->smiObjednat($this->product(ProductTagCode::TRICKO), $customer),
        );
    }

    public function testRestrictedProductNeedsItsOwnPermission(): void
    {
        $customer = $this->createMock(\Uzivatel::class);
        $customer->method('maPravo')
            ->with(Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA)
            ->willReturn(false);

        self::assertFalse(
            (new RestrictedProductRules())->smiObjednat($this->product(ProductTagCode::TRICKO_CERVENE), $customer),
        );
    }

    public function testRestrictedProductIsOrderableWithThePermission(): void
    {
        $customer = $this->createMock(\Uzivatel::class);
        $customer->method('maPravo')->willReturn(true);

        self::assertTrue(
            (new RestrictedProductRules())->smiObjednat($this->product(ProductTagCode::TRICKO_MODRE), $customer),
        );
    }
}
