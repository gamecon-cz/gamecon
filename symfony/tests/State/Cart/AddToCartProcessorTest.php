<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Post;
use App\Dto\Cart\AddToCartInputDto;
use App\Dto\Cart\CartOutputDto;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Service\CartService;
use App\State\Cart\AddToCartProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class AddToCartProcessorTest extends TestCase
{
    private MockObject $cartService;

    private MockObject $entityManager;

    private MockObject $security;

    private AddToCartProcessor $processor;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->processor = new AddToCartProcessor(
            $this->cartService,
            $this->entityManager,
            $this->security,
        );
    }

    public function testAddVariantToCart(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoleMeanings')->willReturn([]);
        $this->security->method('getUser')->willReturn($user);

        $variant = $this->createVariant();
        $this->entityManager->method('find')
            ->with(ProductVariant::class, 42)
            ->willReturn($variant);

        $order = $this->createOrder($user);
        $this->cartService->method('getOrCreateCart')->willReturn($order);
        $this->cartService->expects($this->once())
            ->method('addItem')
            ->with($order, $variant, []);

        $input = new AddToCartInputDto();
        $input->variantId = 42;

        $result = $this->processor->process($input, new Post());

        $this->assertInstanceOf(CartOutputDto::class, $result);
    }

    public function testAddBundleToCart(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoleMeanings')->willReturn([]);
        $this->security->method('getUser')->willReturn($user);

        $bundle = new ProductBundle();
        $bundle->setName('Weekend');
        $bundle->setForced(false);
        $bundle->setApplicableToRoles([]);

        $this->entityManager->method('find')
            ->with(ProductBundle::class, 10)
            ->willReturn($bundle);

        $order = $this->createOrder($user);
        $this->cartService->method('getOrCreateCart')->willReturn($order);
        $this->cartService->expects($this->once())
            ->method('addBundle')
            ->with($order, $bundle, []);

        $input = new AddToCartInputDto();
        $input->variantId = null;
        $input->bundleId = 10;

        $result = $this->processor->process($input, new Post());

        $this->assertInstanceOf(CartOutputDto::class, $result);
    }

    public function testThrowsWhenVariantNotFound(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoleMeanings')->willReturn([]);
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->method('find')->willReturn(null);

        $order = $this->createOrder($user);
        $this->cartService->method('getOrCreateCart')->willReturn($order);

        $input = new AddToCartInputDto();
        $input->variantId = 999;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    private function createVariant(): ProductVariant
    {
        $product = new Product();
        $product->setName('Test');
        $product->setCode('TEST');
        $product->setCurrentPrice('100.00');
        $product->setState(1);
        $product->setDescription('');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('Default');
        $variant->setCode('TEST-V');

        return $variant;
    }

    private function createOrder(User $user): Order
    {
        $order = new Order();
        $order->setCustomer($user);
        $order->setYear(2026);

        return $order;
    }
}
