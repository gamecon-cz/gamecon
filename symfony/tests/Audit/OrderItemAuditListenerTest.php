<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderItemLog;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\OrderItemChangeEnum;
use App\Enum\ProductStateEnum;
use App\EventListener\OrderItemAuditListener;
use App\Service\AuditOriginProvider;
use App\Service\BulkCancelService;
use App\Service\ManualSaleService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Gamecon\Tests\Factory\UserFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Against a real database: what matters is that the row is written inside the transaction of
 * the change it describes, which a mocked connection cannot show.
 */
class OrderItemAuditListenerTest extends AbstractDatabaseKernelTestCase
{
    /**
     * The purchase's id is the log's own column, and the snapshot texts and tags would repeat in every row.
     * The time of purchase is the moment of creation, which the row's changed_at tells.
     *
     * @see OrderItemAuditListener::SNAPSHOT_FIELDS
     */
    private const array LEFT_OUT_ON_PURPOSE = [
        'id_nakupu',
        'product_name',
        'product_code',
        'product_description',
        'product_tags',
        'variant_name',
        'variant_code',
        'datum',
    ];

    public function testCreatedPurchaseIsLoggedWithWhatWasBought(): void
    {
        $customer = $this->createUser('customer');
        $variant = $this->createVariant();

        $item = $this->createPurchase($customer, $variant);

        $log = $this->logOf($item);
        self::assertCount(1, $log);
        self::assertSame(OrderItemChangeEnum::CREATED, $log[0]->getAction());
        self::assertSame((int) $item->getId(), $log[0]->getOrderItemId());
        self::assertSame($customer->getId(), $log[0]->getCustomerId());
        self::assertSame(ROCNIK, $log[0]->getYear());
        self::assertSame([
            'id_uzivatele' => [null, $customer->getId()],
            'variant_id'   => [null, $variant->getId()],
            'rok'          => [null, ROCNIK],
            'cena_nakupni' => [null, '50.00'],
        ], $log[0]->getChanges());
        self::assertNull($log[0]->getChangedById(), 'Nobody is signed in on the console');
        self::assertLessThan(60, abs(time() - $log[0]->getChangedAt()->getTimestamp()));
    }

    public function testCreatedPurchaseRecordsEveryColumnThatIsLogged(): void
    {
        [$item, $expected] = $this->createPurchaseWithEveryColumn();

        $log = $this->logOf($item);

        self::assertCount(1, $log);
        self::assertSame(OrderItemChangeEnum::CREATED, $log[0]->getAction());
        self::assertSame($this->sortedByColumn($expected), $this->sortedByColumn($log[0]->getChanges()));
    }

    public function testRemovedPurchaseRecordsEveryColumnThatIsLogged(): void
    {
        [$item, $expected] = $this->createPurchaseWithEveryColumn();
        $purchaseId = (int) $item->getId();

        $this->entityManager()->remove($item);
        $this->entityManager()->flush();

        $log = $this->logOfPurchase($purchaseId);
        self::assertCount(2, $log);
        self::assertSame(OrderItemChangeEnum::REMOVED, $log[1]->getAction());
        self::assertSame($purchaseId, $log[1]->getOrderItemId());
        self::assertSame($expected['id_uzivatele'][1], $log[1]->getCustomerId());
        self::assertSame(ROCNIK, $log[1]->getYear());
        self::assertSame(
            $this->sortedByColumn(array_map(static fn (array $change): array => [$change[1], null], $expected)),
            $this->sortedByColumn($log[1]->getChanges()),
        );
    }

    public function testOriginColumnHoldsItsMappedLengthAndIsIndexed(): void
    {
        $connection = $this->entityManager()->getConnection();

        self::assertSame('varchar(255)', $connection->fetchOne(<<<'SQL'
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_order_item_log' AND COLUMN_NAME = 'origin'
            SQL));
        self::assertSame([[
            'column_name' => 'origin',
            'sub_part'    => null,
        ]], $connection->fetchAllAssociative(<<<'SQL'
            SELECT COLUMN_NAME AS column_name, SUB_PART AS sub_part
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shop_order_item_log' AND INDEX_NAME = 'IDX_order_item_log_origin'
            ORDER BY SEQ_IN_INDEX
            SQL), 'One column, indexed whole');
    }

    /**
     * A column added to OrderItem later is not recorded on creation or removal until it is named in
     * SNAPSHOT_FIELDS; an update records it from the change set, so only this test notices.
     */
    public function testEveryColumnOfAPurchaseIsLoggedOrLeftOutOnPurpose(): void
    {
        [$item] = $this->createPurchaseWithEveryColumn();
        $recorded = array_keys($this->logOf($item)[0]->getChanges());
        $metadata = $this->entityManager()->getClassMetadata(OrderItem::class);
        $mapped = [];
        foreach ($metadata->getFieldNames() as $field) {
            self::assertNotNull($metadata->getFieldValue($item, $field), "The fixture leaves {$field} empty, and the log skips empty values");
            $mapped[] = $metadata->getColumnName($field);
        }
        foreach ($metadata->getAssociationNames() as $association) {
            if (! $metadata->isSingleValuedAssociation($association)) {
                continue;
            }
            self::assertNotNull($metadata->getFieldValue($item, $association), "The fixture leaves {$association} empty, and the log skips empty values");
            $mapped[] = $metadata->getSingleAssociationJoinColumnName($association);
        }

        self::assertSame(
            [],
            array_values(array_diff($mapped, $recorded, self::LEFT_OUT_ON_PURPOSE)),
            'Neither logged nor listed as left out on purpose',
        );
        self::assertSame(
            [],
            array_values(array_diff(self::LEFT_OUT_ON_PURPOSE, $mapped)),
            'Listed as left out, but the entity no longer has the column',
        );
        self::assertSame(
            [],
            array_values(array_intersect(self::LEFT_OUT_ON_PURPOSE, $recorded)),
            'Listed as left out, but it is logged',
        );
    }

    public function testRepricingLogsOnlyTheColumnsThatChanged(): void
    {
        $item = $this->createPurchase($this->createUser('customer'), $this->createVariant());

        $item->setPurchasePrice('0.00');
        $item->setDiscountAmount('50.00');
        $item->setDiscountReason('Jedno tričko zdarma');
        $this->entityManager()->flush();

        $log = $this->logOf($item);
        self::assertCount(2, $log);
        self::assertSame(OrderItemChangeEnum::UPDATED, $log[1]->getAction());
        self::assertSame([
            'cena_nakupni'    => ['50.00', '0.00'],
            'discount_amount' => [null, '50.00'],
            'discount_reason' => [null, 'Jedno tričko zdarma'],
        ], $log[1]->getChanges());
    }

    public function testFlushWithoutChangesLogsNothing(): void
    {
        $item = $this->createPurchase($this->createUser('customer'), $this->createVariant());

        $this->entityManager()->flush();

        self::assertCount(1, $this->logOf($item));
    }

    public function testRemovedPurchaseIsLoggedAndTheLogOutlivesIt(): void
    {
        $item = $this->createPurchase($this->createUser('customer'), $this->createVariant());
        $purchaseId = (int) $item->getId();

        $this->entityManager()->remove($item);
        $this->entityManager()->flush();

        self::assertSame(
            0,
            (int) $this->connection()->fetchOne(
                <<<'SQL'
                SELECT COUNT(*) FROM shop_nakupy WHERE id_nakupu = :id
                SQL,
                [
                    'id' => $purchaseId,
                ],
            ),
            'The purchase itself is gone',
        );
        $log = $this->logOfPurchase($purchaseId);
        self::assertCount(2, $log);
        self::assertSame(OrderItemChangeEnum::REMOVED, $log[1]->getAction());
        self::assertSame($purchaseId, $log[1]->getOrderItemId(), 'Doctrine clears the id before postRemove');
        self::assertSame('50.00', $log[1]->getChanges()['cena_nakupni'][0]);
        self::assertNull($log[1]->getChanges()['cena_nakupni'][1]);
    }

    public function testRemovingAPurchaseThatWasNeverInsertedLogsNothing(): void
    {
        $customer = $this->createUser('customer');
        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setVariant($this->createVariant());
        $item->setYear(ROCNIK);
        $item->setPurchasePrice('50.00');

        $this->entityManager()->persist($item);
        $this->entityManager()->remove($item);
        $this->entityManager()->flush();

        self::assertSame(0, $this->countLogOf($customer));
    }

    public function testPurchaseWithoutCustomerIsRefusedAsSuch(): void
    {
        $item = $this->purchaseWithIdAndCustomer(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A purchase always has a customer.');

        $this->auditListener()->postPersist($item, new PostPersistEventArgs($item, $this->entityManager()));
    }

    public function testPurchaseOfCustomerWithoutIdIsRefusedAsSuch(): void
    {
        $item = $this->purchaseWithIdAndCustomer(new User());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The customer of the purchase has no id yet.');

        $this->auditListener()->postPersist($item, new PostPersistEventArgs($item, $this->entityManager()));
    }

    public function testRemovalInTheSameFlushAsItsOrderStillKnowsTheOrder(): void
    {
        $customer = $this->createUser('customer');
        $order = new Order();
        $order->setCustomer($customer);
        $order->setYear(ROCNIK);
        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setVariant($this->createVariant());
        $item->setYear(ROCNIK);
        $item->setPurchasePrice('50.00');
        $item->setOrder($order);
        $order->addItem($item);
        $this->entityManager()->persist($order);
        $this->entityManager()->flush();
        $orderId = (int) $order->getId();
        $purchaseId = (int) $item->getId();

        $this->entityManager()->remove($order);
        $this->entityManager()->flush();

        $log = $this->logOfPurchase($purchaseId);
        self::assertSame(OrderItemChangeEnum::REMOVED, $log[1]->getAction());
        self::assertSame([$orderId, null], $log[1]->getChanges()['order_id'], 'Doctrine has cleared the order id by postRemove');
    }

    public function testLogRowIsRolledBackWithTheChange(): void
    {
        $customer = $this->createUser('customer');
        $variant = $this->createVariant();

        $this->connection()->beginTransaction();
        $this->createPurchase($customer, $variant);
        $this->connection()->rollBack();
        $this->entityManager()->clear();

        self::assertSame(0, $this->countLogOf($customer), 'A change that never happened must leave no trace');
    }

    public function testFailingLogInsertAbortsThePurchase(): void
    {
        $customer = $this->createUser('customer');
        $variant = $this->createVariant();
        $unavailableOrigin = new class(static::getContainer()->get(Security::class), static::getContainer()->get(RequestStack::class)) extends AuditOriginProvider {
            public function getOrigin(): string
            {
                throw new \RuntimeException('The log is unavailable');
            }
        };
        $this->entityManager()->getConfiguration()->getEntityListenerResolver()->register(
            new OrderItemAuditListener($unavailableOrigin, new NativeClock()),
        );

        try {
            $this->createPurchase($customer, $variant);
            self::fail('A purchase must not go through without its log row');
        } catch (\RuntimeException $failure) {
            self::assertSame('The log is unavailable', $failure->getMessage());
        }

        self::assertSame(
            0,
            (int) $this->connection()->fetchOne(
                <<<'SQL'
                SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :customer
                SQL,
                [
                    'customer' => $customer->getId(),
                ],
            ),
        );
    }

    public function testActorAndOriginComeFromTheRequestAndTheToken(): void
    {
        $actor = $this->createUser('operator');
        $request = new Request();
        $request->attributes->set('_route', 'admin_test_route');
        static::getContainer()->get(RequestStack::class)->push($request);
        static::getContainer()->get(TokenStorageInterface::class)->setToken(
            new UsernamePasswordToken($actor, 'main', $actor->getRoles()),
        );

        $item = $this->createPurchase($this->createUser('customer'), $this->createVariant());

        $log = $this->logOf($item);
        self::assertSame($actor->getId(), $log[0]->getChangedById());
        self::assertSame('admin_test_route', $log[0]->getOrigin());
    }

    public function testManualSaleLogsEveryPieceWithTheOperator(): void
    {
        $customer = $this->createUser('customer');
        $operator = $this->createUser('operator');
        $variant = $this->createVariant();

        static::getContainer()->get(ManualSaleService::class)->sell($customer, $operator, $variant, 2, '50.00', ROCNIK);

        $log = $this->entityManager()->getRepository(OrderItemLog::class)->findBy([
            'customerId' => $customer->getId(),
        ], [
            'id' => 'ASC',
        ]);
        self::assertCount(2, $log, 'One row per piece sold');
        foreach ($log as $row) {
            self::assertSame(OrderItemChangeEnum::CREATED, $row->getAction());
            self::assertSame($operator->getId(), $row->getChanges()['id_objednatele'][1], 'The orderer is part of what was recorded');
        }
    }

    public function testBulkCancellationLogsTheRemoval(): void
    {
        $item = $this->createPurchase($this->createUser('customer'), $this->createVariant());
        $purchaseId = (int) $item->getId();

        static::getContainer()->get(BulkCancelService::class)->removePurchases([$purchaseId]);

        self::assertSame(
            [OrderItemChangeEnum::CREATED, OrderItemChangeEnum::REMOVED],
            array_map(static fn (OrderItemLog $row): OrderItemChangeEnum => $row->getAction(), $this->logOfPurchase($purchaseId)),
        );
    }

    private function createUser(string $prefix): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => $prefix . '_' . uniqid(),
            UserEntityStructure::email => $prefix . '_' . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'Audit Testovací',
        ])->_save()->_real();

        return $user;
    }

    private function createVariant(): ProductVariant
    {
        $code = 'audit-' . uniqid();

        $product = new Product();
        $product->setName('Placka');
        $product->setCode($code);
        $product->setCurrentPrice('50.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('jedna velikost');
        $variant->setCode($code . '-1');
        $variant->setCapacity(null);
        $variant->setPrice('50.00');
        $variant->setPosition(0);
        $product->addVariant($variant);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return $variant;
    }

    private function createPurchase(User $customer, ProductVariant $variant): OrderItem
    {
        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setVariant($variant);
        $item->setYear(ROCNIK);
        $item->setPurchasePrice('50.00');
        $this->entityManager()->persist($item);
        $this->entityManager()->flush();

        return $item;
    }

    private function auditListener(): OrderItemAuditListener
    {
        return new OrderItemAuditListener(
            new AuditOriginProvider(static::getContainer()->get(Security::class), static::getContainer()->get(RequestStack::class)),
            new NativeClock(),
        );
    }

    /**
     * A purchase as the listener sees it after the insert, without going through one.
     */
    private function purchaseWithIdAndCustomer(?User $customer): OrderItem
    {
        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setYear(ROCNIK);
        $item->setPurchasePrice('50.00');
        (new \ReflectionProperty(OrderItem::class, 'id'))->setValue($item, 1);

        return $item;
    }

    /**
     * @return array{0: OrderItem, 1: array<string, array{0: null, 1: mixed}>} the purchase and what its creation should record, by column
     */
    private function createPurchaseWithEveryColumn(): array
    {
        $customer = $this->createUser('customer');
        $orderer = $this->createUser('orderer');
        $operator = $this->createUser('operator');
        $variant = $this->createVariant();
        $order = new Order();
        $order->setCustomer($customer);
        $order->setYear(ROCNIK);
        $bundle = new ProductBundle();
        $bundle->setName('Balíček na víkend');
        $this->entityManager()->persist($order);
        $this->entityManager()->persist($bundle);
        $this->entityManager()->flush();

        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setOrderer($orderer);
        $item->setOrder($order);
        $item->setVariant($variant);
        $item->setBundle($bundle);
        $item->setYear(ROCNIK);
        // Filled so that a left-out column which started to be logged would show up as an extra one.
        $item->setProductName('Placka');
        $item->setProductCode('placka');
        $item->setProductDescription('Placka s logem');
        $item->setProductTags(['merch']);
        $item->setVariantName('jedna velikost');
        $item->setVariantCode('placka-1');
        $item->setPurchasePrice('40.00');
        $item->setOriginalPrice('50.00');
        $item->setDiscountAmount('10.00');
        $item->setDiscountReason('Organizátor - kostka zdarma');
        $item->setDiscountSnapshot([
            'rule'      => 'organizer',
            'threshold' => 3,
        ]);
        $item->recordOverride('deadline', 'desk', $operator, new \DateTimeImmutable('2026-07-01T10:00:00+02:00'));
        $this->entityManager()->persist($item);
        $this->entityManager()->flush();

        return [$item, [
            'id_uzivatele'      => [null, $customer->getId()],
            'id_objednatele'    => [null, $orderer->getId()],
            'order_id'          => [null, $order->getId()],
            'variant_id'        => [null, $variant->getId()],
            'bundle_id'         => [null, $bundle->getId()],
            'rok'               => [null, ROCNIK],
            'cena_nakupni'      => [null, '40.00'],
            'original_price'    => [null, '50.00'],
            'discount_amount'   => [null, '10.00'],
            'discount_reason'   => [null, 'Organizátor - kostka zdarma'],
            'discount_snapshot' => [null, [
                'rule'      => 'organizer',
                'threshold' => 3,
            ]],
            'override_log' => [null, [[
                'guard'  => 'deadline',
                'source' => 'desk',
                'by'     => $operator->getId(),
                'at'     => '2026-07-01T10:00:00+02:00',
            ]]],
        ]];
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function sortedByColumn(array $changes): array
    {
        ksort($changes);

        return $changes;
    }

    /**
     * @return list<OrderItemLog>
     */
    private function logOf(OrderItem $item): array
    {
        return $this->logOfPurchase((int) $item->getId());
    }

    /**
     * @return list<OrderItemLog>
     */
    private function logOfPurchase(int $purchaseId): array
    {
        return $this->entityManager()->getRepository(OrderItemLog::class)->findBy([
            'orderItemId' => $purchaseId,
        ], [
            'id' => 'ASC',
        ]);
    }

    private function countLogOf(User $customer): int
    {
        return (int) $this->connection()->fetchOne(
            <<<'SQL'
            SELECT COUNT(*) FROM shop_order_item_log WHERE customer_id = :customer
            SQL,
            [
                'customer' => $customer->getId(),
            ],
        );
    }
}
