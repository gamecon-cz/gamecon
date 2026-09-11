<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\EventListener\UserRoleChangedListener;
use App\Repository\OrderRepository;
use App\Service\DiscountCalculator;
use App\Service\PriceIncreaseNotifier;
use App\Service\RestrictedProductRules;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The CFO is told only when a role change makes an order dearer, so the rule deciding that
 * is what needs pinning — a notification on every change would be noise nobody reads.
 */
class UserRoleChangedListenerTest extends TestCase
{
    private MockObject $discountCalculator;

    private MockObject $priceIncreaseNotifier;

    private MockObject $orderRepository;

    private bool $smiObjednat = true;

    protected function setUp(): void
    {
        $this->discountCalculator = $this->createMock(DiscountCalculator::class);
        $this->priceIncreaseNotifier = $this->createMock(PriceIncreaseNotifier::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
    }

    private function spustProCenu(string $puvodni, string $nova): void
    {
        $product = new Product();
        $product->setName('Tričko');
        $product->setCurrentPrice('250.00');

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setPurchasePrice($puvodni);

        $order = new Order();
        $order->addItem($item);

        $user = $this->createMock(User::class);
        $this->orderRepository->method('findPendingForCustomer')->willReturn($order);
        $this->discountCalculator->method('calculateDiscount')->willReturn([
            'discount'       => null,
            'discountAmount' => '0.00',
            'finalPrice'     => $nova,
            'reason'         => null,
        ]);

        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->orderRepository;
        /** @var DiscountCalculator $discountCalculator */
        $discountCalculator = $this->discountCalculator;
        /** @var PriceIncreaseNotifier $notifier */
        $notifier = $this->priceIncreaseNotifier;
        $restrictedProductRules = $this->createMock(RestrictedProductRules::class);
        $restrictedProductRules->method('smiObjednat')->willReturn($this->smiObjednat);
        $restrictedProductRules->method('dejLegacyUzivatele')
            ->willReturn($this->createMock(\Uzivatel::class));
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);

        (new UserRoleChangedListener(
            $entityManager,
            $orderRepository,
            $discountCalculator,
            $notifier,
            $restrictedProductRules,
            $logger,
        ))->onUserRoleChanged($user, 2026);
    }

    public function testDearerOrderNotifiesTheCfo(): void
    {
        $this->priceIncreaseNotifier->expects(self::once())
            ->method('oznamZdrazeni')
            ->with(self::anything(), 2026, self::callback(
                static fn (array $zdrazeni): bool => count($zdrazeni) === 1,
            ));

        $this->spustProCenu('0.00', '250.00');
    }

    public function testItemTheCustomerMayNoLongerOrderKeepsItsPrice(): void
    {
        // They ordered the red t-shirt while entitled; losing the right must not reprice it.
        $this->smiObjednat = false;
        $this->priceIncreaseNotifier->expects(self::never())->method('oznamZdrazeni');

        $this->spustProCenu('0.00', '250.00');
    }

    public function testCheaperOrderNotifiesNobody(): void
    {
        $this->priceIncreaseNotifier->expects(self::once())
            ->method('oznamZdrazeni')
            ->with(self::anything(), 2026, []);

        $this->spustProCenu('250.00', '0.00');
    }
}
