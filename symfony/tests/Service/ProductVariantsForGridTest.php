<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Repository\OrderItemRepository;
use App\Service\CapacityManager;
use App\Service\ProductVariantsForGrid;
use PHPUnit\Framework\TestCase;

class ProductVariantsForGridTest extends TestCase
{
    /**
     * @param array<string, int> $koupenoPodleVelikosti
     *
     * @return array<string, ?int> max quantity per offered size
     */
    private function nabidka(Product $product, array $koupenoPodleVelikosti = []): array
    {
        $orderItems = $this->createMock(OrderItemRepository::class);
        $orderItems->method('countCustomerPurchases')->willReturnCallback(
            static fn (User $customer, Product $product, int $year, ProductVariant $variant): int => $koupenoPodleVelikosti[$variant->getName()] ?? 0,
        );
        $capacity = $this->createMock(CapacityManager::class);
        $capacity->method('remainingByVariant')->willReturn([]);

        $nabidka = [];
        foreach ((new ProductVariantsForGrid($orderItems, $capacity))->pro($product, $this->createMock(User::class), 2026, []) as $varianta) {
            $nabidka[(string) $varianta->name] = $varianta->maxQuantity;
        }

        return $nabidka;
    }

    private function tricko(ProductStateEnum $stavVelikostiM): Product
    {
        $product = new Product();
        $product->setName('Tričko');
        $product->setCode('TRICKO');
        $product->setCurrentPrice('250.00');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setDescription('');
        foreach ([
            'S' => ProductStateEnum::PUBLIC,
            'M' => $stavVelikostiM,
        ] as $velikost => $stav) {
            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setName($velikost);
            $variant->setCode('TRICKO-' . $velikost);
            $variant->setState($stav);
            (new \ReflectionProperty(ProductVariant::class, 'id'))->setValue($variant, $velikost === 'S' ? 1 : 2);
            $product->addVariant($variant);
        }

        return $product;
    }

    /**
     * The cart refuses a paused or withdrawn size, so the grid must not offer it.
     */
    public function testAPausedSizeIsNotOffered(): void
    {
        self::assertSame(['S'], array_keys($this->nabidka($this->tricko(ProductStateEnum::SUSPENDED))));
        self::assertSame(['S'], array_keys($this->nabidka($this->tricko(ProductStateEnum::RETIRED))));
    }

    /**
     * Still shown to whoever holds it, so it can be dropped, but no more than they hold.
     */
    public function testAPausedSizeAlreadyBoughtStaysCappedAtWhatIsHeld(): void
    {
        $nabidka = $this->nabidka($this->tricko(ProductStateEnum::SUSPENDED), [
            'M' => 2,
        ]);

        self::assertSame(2, $nabidka['M']);
        self::assertNull($nabidka['S'], 'Neomezená velikost zůstává bez stropu');
    }
}
