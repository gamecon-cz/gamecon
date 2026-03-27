<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Delete;
use App\Dto\Cart\CartOutputDto;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Service\CartService;
use App\State\Cart\RemoveFromCartProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class RemoveFromCartProcessorTest extends TestCase
{
    private MockObject $cartService;

    private MockObject $entityManager;

    private MockObject $security;

    private RemoveFromCartProcessor $processor;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->processor = new RemoveFromCartProcessor(
            $this->cartService,
            $this->entityManager,
            $this->security,
        );
    }

    public function testRemoveItemFromCart(): void
    {
        $user = $this->createConfiguredMock(User::class, [
            'getId' => 1,
            'getRoleMeanings' => [],
        ]);
        $this->security->method('getUser')->willReturn($user);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $item = new OrderItem();
        $item->setCustomer($user);
        $item->setOrder($order);
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $order->addItem($item);

        $ref = new \ReflectionProperty(OrderItem::class, 'id');
        $ref->setValue($item, 77);

        $this->entityManager->method('find')
            ->with(OrderItem::class, 77)
            ->willReturn($item);

        $this->cartService->expects($this->once())
            ->method('removeItem')
            ->with($order, $item, []);

        $result = $this->processor->process(null, new Delete(), ['itemId' => 77]);

        $this->assertInstanceOf(CartOutputDto::class, $result);
    }

    public function testRemoveBundleItemRemovesWholeBundleForParticipant(): void
    {
        $user = $this->createConfiguredMock(User::class, [
            'getId' => 1,
            'getRoleMeanings' => [RoleMeaning::PRIHLASEN],
        ]);
        $this->security->method('getUser')->willReturn($user);

        $bundle = new ProductBundle();
        $bundle->setName('Weekend');
        $bundle->setForced(true);
        $bundle->setApplicableToRoles([RoleMeaning::PRIHLASEN->value]);

        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        $item = new OrderItem();
        $item->setCustomer($user);
        $item->setOrder($order);
        $item->setBundle($bundle);
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);
        $order->addItem($item);

        $ref = new \ReflectionProperty(OrderItem::class, 'id');
        $ref->setValue($item, 88);

        $this->entityManager->method('find')
            ->with(OrderItem::class, 88)
            ->willReturn($item);

        // Should remove bundle, not individual item
        $this->cartService->expects($this->once())
            ->method('removeBundle')
            ->with($order, $bundle);
        $this->cartService->expects($this->never())
            ->method('removeItem');

        $result = $this->processor->process(null, new Delete(), ['itemId' => 88]);

        $this->assertInstanceOf(CartOutputDto::class, $result);
    }

    public function testThrowsWhenItemNotFound(): void
    {
        $user = $this->createConfiguredMock(User::class, [
            'getId' => 1,
            'getRoleMeanings' => [],
        ]);
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->method('find')->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['itemId' => 999]);
    }

    public function testThrowsWhenItemBelongsToOtherUser(): void
    {
        $currentUser = $this->createConfiguredMock(User::class, ['getId' => 1]);
        $otherUser = $this->createConfiguredMock(User::class, ['getId' => 2]);
        $this->security->method('getUser')->willReturn($currentUser);

        $item = new OrderItem();
        $item->setCustomer($otherUser);
        $item->setPurchasePrice('100.00');
        $item->setYear(2026);

        $ref = new \ReflectionProperty(OrderItem::class, 'id');
        $ref->setValue($item, 55);

        $this->entityManager->method('find')
            ->with(OrderItem::class, 55)
            ->willReturn($item);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $this->processor->process(null, new Delete(), ['itemId' => 55]);
    }
}
