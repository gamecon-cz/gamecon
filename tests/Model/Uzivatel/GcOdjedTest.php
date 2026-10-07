<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\ProductStateEnum;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Recording a departure at the infopult also completes the participant's order, because only a
 * completed order keeps its prices when a role changes.
 */
class GcOdjedTest extends AbstractTestDb
{
    private const ODJIZDEJICI = 77001;
    private const INFOPULTAK = 77002;

    protected static bool $disableStrictTransTables = true;

    private EntityManagerInterface $em;

    private Connection $connection;

    protected static array $initQueries = [
        <<<'SQL'
        INSERT INTO uzivatele_hodnoty (id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, pohlavi)
        VALUES (77001, 'odjizdejici', 'Odjíždějící', 'Účastník', 'odjizdejici@example.invalid', 'f'),
               (77002, 'infopultak', 'Infopult', 'Obsluha', 'infopultak@example.invalid', 'm')
        SQL,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->connection = self::getContainer()->get(Connection::class);
        $this->em->clear();

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT IGNORE INTO uzivatele_role (id_uzivatele, id_role, posadil) VALUES (:user, :role, :posadil)
            SQL,
            [
                'user'    => self::ODJIZDEJICI,
                'role'    => Role::PRITOMEN_NA_LETOSNIM_GC,
                'posadil' => self::INFOPULTAK,
            ],
        );
    }

    public function testDepartureCompletesTheOrderAndRecordsTheDepartureRole(): void
    {
        $order = $this->createPendingOrder();

        $this->odjizdejici()->gcOdjed($this->infopultak());

        self::assertTrue($this->odjizdejici()->maRoli(Role::ODJEL_Z_LETOSNIHO_GC));
        self::assertSame(Order::STATUS_COMPLETED, $this->statusOf($order));
    }

    /**
     * Adding the role reprices a pending order, so completing first would freeze the prices as
     * they were before the departure role, and a later change would no longer be seen.
     */
    public function testOrderIsStillPendingWhenTheDepartureRoleIsRecorded(): void
    {
        $order = $this->createPendingOrder();
        $statusWhenRoleWasPersisted = new \ArrayObject();
        $legacyEntityManager = SystemoveNastaveni::zGlobals()->kernel()->getContainer()->get('doctrine.orm.entity_manager');
        $listener = new class($order, $legacyEntityManager->getConnection(), $statusWhenRoleWasPersisted) {
            /**
             * @param \ArrayObject<int, string> $statusWhenRoleWasPersisted
             */
            public function __construct(
                private readonly Order $order,
                private readonly Connection $connection,
                private readonly \ArrayObject $statusWhenRoleWasPersisted,
            ) {
            }

            public function postPersist(PostPersistEventArgs $event): void
            {
                if ($event->getObject() instanceof UserRole) {
                    $this->statusWhenRoleWasPersisted[] = (string) $this->connection->fetchOne(
                        <<<'SQL'
                        SELECT status FROM shop_order WHERE id = :id
                        SQL,
                        [
                            'id' => $this->order->getId(),
                        ],
                    );
                }
            }
        };
        $legacyEntityManager->getEventManager()->addEventListener(Events::postPersist, $listener);

        try {
            $this->odjizdejici()->gcOdjed($this->infopultak());
        } finally {
            $legacyEntityManager->getEventManager()->removeEventListener(Events::postPersist, $listener);
        }

        self::assertSame([Order::STATUS_PENDING], $statusWhenRoleWasPersisted->getArrayCopy());
        self::assertSame(Order::STATUS_COMPLETED, $this->statusOf($order));
    }

    public function testDepartureWithoutAnOrderStillRecordsTheRole(): void
    {
        $this->odjizdejici()->gcOdjed($this->infopultak());

        self::assertTrue($this->odjizdejici()->maRoli(Role::ODJEL_Z_LETOSNIHO_GC));
    }

    public function testRepeatedDepartureKeepsTheFirstCompletion(): void
    {
        $order = $this->createPendingOrder();
        $this->odjizdejici()->gcOdjed($this->infopultak());
        // Backdated, because completed_at has second precision and both departures fall in one second.
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE shop_order SET completed_at = '2000-01-01 00:00:00' WHERE id = :id
            SQL,
            [
                'id' => $order->getId(),
            ],
        );

        $this->odjizdejici()->gcOdjed($this->infopultak());

        self::assertSame('2000-01-01 00:00:00', $this->completedAtOf($order));
    }

    /**
     * Production holds an account whose gender Doctrine cannot read; the departure of such a
     * participant must not depend on loading the whole entity.
     */
    public function testDepartureOfAParticipantDoctrineCannotHydrateStillCompletesTheOrder(): void
    {
        $order = $this->createPendingOrder();
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE uzivatele_hodnoty SET pohlavi = '' WHERE id_uzivatele = :id
            SQL,
            [
                'id' => self::ODJIZDEJICI,
            ],
        );
        $this->em->clear();

        $this->odjizdejici()->gcOdjed($this->infopultak());

        self::assertSame(Order::STATUS_COMPLETED, $this->statusOf($order));
    }

    /**
     * The infopult button is disabled once the departure is recorded, so a failure after the role
     * was written could never be retried from the page.
     */
    public function testFailedCompletionTakesTheDepartureRoleBackWithIt(): void
    {
        $order = $this->createPendingOrder();
        $legacyContainer = SystemoveNastaveni::zGlobals()->kernel()->getContainer();
        $legacyEntityManager = $legacyContainer->get('doctrine.orm.entity_manager');
        $failOnCompletion = new class {
            public function preUpdate(PreUpdateEventArgs $event): void
            {
                if ($event->getObject() instanceof Order && $event->hasChangedField('status')) {
                    throw new \RuntimeException('completion failed');
                }
            }
        };
        $legacyEntityManager->getEventManager()->addEventListener(Events::preUpdate, $failOnCompletion);

        try {
            $this->odjizdejici()->gcOdjed($this->infopultak());
            self::fail('The failing completion should have surfaced');
        } catch (\RuntimeException $exception) {
            self::assertSame('completion failed', $exception->getMessage());
        } finally {
            $legacyEntityManager->getEventManager()->removeEventListener(Events::preUpdate, $failOnCompletion);
            // A failed flush closes the manager, and this one is shared by the rest of the run.
            $legacyContainer->get('doctrine')->resetManager();
        }

        self::assertSame(0, $this->countOfDepartureRoles());
        self::assertSame(Order::STATUS_PENDING, $this->statusOf($order));
    }

    private function odjizdejici(): \Uzivatel
    {
        return \Uzivatel::zIdUrcite(self::ODJIZDEJICI);
    }

    private function infopultak(): \Uzivatel
    {
        return \Uzivatel::zIdUrcite(self::INFOPULTAK);
    }

    private function createPendingOrder(): Order
    {
        $customer = $this->em->find(User::class, self::ODJIZDEJICI);
        self::assertNotNull($customer);

        $code = 'gcodjed-' . uniqid();
        $product = new Product();
        $product->setName('Tričko');
        $product->setCode($code);
        $product->setCurrentPrice('250.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $this->em->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('M');
        $variant->setCode($code . '-m');
        $variant->setCapacity(10);
        $variant->setPrice('250.00');
        $variant->setPosition(0);
        $product->addVariant($variant);
        $this->em->persist($variant);

        $order = new Order();
        $order->setCustomer($customer);
        $order->setYear(ROCNIK);
        $this->em->persist($order);

        $item = new OrderItem();
        $item->setCustomer($customer);
        $item->setOrder($order);
        $item->setVariant($variant);
        $item->setYear(ROCNIK);
        $item->setPurchasePrice('250.00');
        $order->addItem($item);
        $this->em->persist($item);

        $this->em->flush();

        return $order;
    }

    private function countOfDepartureRoles(): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
            SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = :user AND id_role = :role
            SQL,
            [
                'user' => self::ODJIZDEJICI,
                'role' => Role::ODJEL_Z_LETOSNIHO_GC,
            ],
        );
    }

    private function statusOf(Order $order): string
    {
        return (string) $this->connection->fetchOne(
            <<<'SQL'
            SELECT status FROM shop_order WHERE id = :id
            SQL,
            [
                'id' => $order->getId(),
            ],
        );
    }

    private function completedAtOf(Order $order): ?string
    {
        $completedAt = $this->connection->fetchOne(
            <<<'SQL'
            SELECT completed_at FROM shop_order WHERE id = :id
            SQL,
            [
                'id' => $order->getId(),
            ],
        );

        return $completedAt === false ? null : $completedAt;
    }
}
