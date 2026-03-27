<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Get;
use App\Dto\Cart\CartOutputDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Service\CartService;
use App\State\Cart\CartProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CartProviderTest extends TestCase
{
    private MockObject $cartService;

    private MockObject $security;

    private CartProvider $provider;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->security = $this->createMock(Security::class);

        $this->provider = new CartProvider(
            $this->cartService,
            $this->security,
        );
    }

    public function testReturnsCartWhenExists(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $item = new OrderItem();
        $item->setPurchasePrice('250.00');
        $item->setYear(2026);
        $item->setCustomer($user);
        $order->addItem($item);
        $order->recalculateTotal();

        $this->cartService->method('getCart')->willReturn($order);

        $result = $this->provider->provide(new Get());

        $this->assertInstanceOf(CartOutputDto::class, $result);
        $this->assertSame('250.00', $result->totalPrice);
        $this->assertSame(1, $result->itemCount);
        $this->assertSame('pending', $result->status);
    }

    public function testReturnsEmptyCartWhenNone(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->cartService->method('getCart')->willReturn(null);

        $result = $this->provider->provide(new Get());

        $this->assertInstanceOf(CartOutputDto::class, $result);
        $this->assertNull($result->id);
        $this->assertSame('0.00', $result->totalPrice);
        $this->assertSame(0, $result->itemCount);
        $this->assertSame('empty', $result->status);
    }
}
