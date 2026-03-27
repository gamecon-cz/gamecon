<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Post;
use App\Dto\Cart\CartOutputDto;
use App\Dto\Cart\CheckoutInputDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Service\CartService;
use App\State\Cart\CheckoutProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CheckoutProcessorTest extends TestCase
{
    private MockObject $cartService;

    private MockObject $entityManager;

    private MockObject $security;

    private CheckoutProcessor $processor;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->processor = new CheckoutProcessor(
            $this->cartService,
            $this->entityManager,
            $this->security,
        );
    }

    public function testCheckoutCompletesOrder(): void
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

        $this->cartService->method('getCart')->willReturn($order);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->processor->process(new CheckoutInputDto(), new Post());

        $this->assertInstanceOf(CartOutputDto::class, $result);
        $this->assertTrue($order->isCompleted());
    }

    public function testCheckoutThrowsOnEmptyCart(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->cartService->method('getCart')->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('prázdný');

        $this->processor->process(new CheckoutInputDto(), new Post());
    }

    public function testCheckoutThrowsOnAlreadyCompletedOrder(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $item = new OrderItem();
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $item->setCustomer($user);
        $order->addItem($item);
        $order->complete();

        $this->cartService->method('getCart')->willReturn($order);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('dokončena nebo zrušena');

        $this->processor->process(new CheckoutInputDto(), new Post());
    }
}
