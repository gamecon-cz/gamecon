<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\RoleMeaning;
use App\Repository\OrderRepository;
use App\Repository\ProductBundleRepository;
use App\Service\CapacityManager;
use App\Service\CartService;
use App\Service\CurrentYearProvider;
use App\Service\DiscountCalculator;
use App\Service\RoleHistoryRecalculator;
use App\Service\UserRoleService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Integration tests with real database for the new e-shop services.
 *
 * Note: uses Doctrine EM for all data setup/reads (not legacy dbQuery)
 * to avoid cross-connection transaction isolation issues.
 */
class EshopIntegrationTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private EntityManagerInterface $em;
    private Connection $connection;
    private static ?Product $product = null;
    private static ?ProductVariant $variantM = null;
    private static ?ProductVariant $variantL = null;

    protected static array $initQueries = [];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                /** @var EntityManagerInterface $em */
                $em = static::getContainer()->get('doctrine.orm.entity_manager');

                // Create test users via DBAL (same connection as Doctrine)
                /** @var Connection $conn */
                $conn = static::getContainer()->get(Connection::class);
                $conn->executeStatement("INSERT INTO uzivatele_hodnoty SET
                    id_uzivatele = 89901, login_uzivatele = 'eshoptest',
                    jmeno_uzivatele = 'Eshop', prijmeni_uzivatele = 'Tester',
                    email1_uzivatele = 'eshop.tester@test.cz', pohlavi = 'm',
                    datum_narozeni = '1990-01-01'");
                $conn->executeStatement("INSERT INTO uzivatele_hodnoty SET
                    id_uzivatele = 89902, login_uzivatele = 'editor',
                    jmeno_uzivatele = 'Editor', prijmeni_uzivatele = 'Admin',
                    email1_uzivatele = 'editor@test.cz', pohlavi = 'm',
                    datum_narozeni = '1985-06-15'");

                // Create product
                $product = new Product();
                $product->setName('Tričko modré');
                $product->setCode('eshoptest-tricko-modre');
                $product->setCurrentPrice('250.00');
                $product->setState(1);
                $product->setDescription('Modré tričko');
                $product->setAvailableUntil(new \DateTimeImmutable('+1 year'));
                $product->setReservedForOrganizers(2);

                // Assign tag (needed for backward-compatible view shop_predmety_s_typem)
                $trickoTag = $em->getRepository(\App\Entity\ProductTag::class)->findOneBy([
                    'code' => 'tricko',
                ]);
                if ($trickoTag !== null) {
                    $product->addTag($trickoTag);
                }

                $em->persist($product);

                // Variant M
                $variantM = new ProductVariant();
                $variantM->setProduct($product);
                $variantM->setName('M');
                $variantM->setCode('eshoptest-tricko-modre-m');
                $variantM->setRemainingQuantity(10);
                $variantM->setPosition(0);
                $product->addVariant($variantM);
                $em->persist($variantM);

                // Variant L (limited stock)
                $variantL = new ProductVariant();
                $variantL->setProduct($product);
                $variantL->setName('L');
                $variantL->setCode('eshoptest-tricko-modre-l');
                $variantL->setRemainingQuantity(1);
                $variantL->setPosition(1);
                $product->addVariant($variantL);
                $em->persist($variantL);

                $em->flush();

                // Store IDs for later
                self::$product = $product;
                self::$variantM = $variantM;
                self::$variantL = $variantL;
            },
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->connection = self::getContainer()->get(Connection::class);
        $this->em->clear();
    }

    // ==================== 1. CurrentYearProvider ====================

    public function testCurrentYearProviderReadsRocnikConstant(): void
    {
        $provider = new CurrentYearProvider();

        $this->assertSame(ROCNIK, $provider->getCurrentYear());
    }

    // ==================== 2. CapacityManager with real DB ====================

    public function testCapacityManagerAtomicPurchaseDecrementsStock(): void
    {
        $capacityManager = new CapacityManager($this->connection, $this->em);
        $variant = $this->em->find(ProductVariant::class, self::$variantM->getId());
        $this->assertNotNull($variant);

        $capacityManager->purchase($variant, 1);

        $dbStock = (int) $this->connection->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => self::$variantM->getId(),
            ],
        );
        $this->assertSame(9, $dbStock);
        $this->assertSame(9, $variant->getRemainingQuantity());
    }

    public function testCapacityManagerPurchaseThrowsWhenSoldOut(): void
    {
        $capacityManager = new CapacityManager($this->connection, $this->em);
        $variant = $this->em->find(ProductVariant::class, self::$variantL->getId());
        $this->assertNotNull($variant);

        $this->expectException(\RuntimeException::class);
        $capacityManager->purchase($variant, 2); // want 2, only 1 available
    }

    public function testCapacityManagerCancelPurchaseIncrementsStock(): void
    {
        $capacityManager = new CapacityManager($this->connection, $this->em);
        $variant = $this->em->find(ProductVariant::class, self::$variantM->getId());
        $initialStock = $variant->getRemainingQuantity();

        $capacityManager->cancelPurchase($variant, 1);

        $dbStock = (int) $this->connection->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => self::$variantM->getId(),
            ],
        );
        $this->assertSame($initialStock + 1, $dbStock);
    }

    public function testCapacityManagerReservedForOrganizersInheritsFromProduct(): void
    {
        $capacityManager = new CapacityManager($this->connection, $this->em);
        $variant = $this->em->find(ProductVariant::class, self::$variantM->getId());

        $this->assertNull($variant->getReservedForOrganizers());
        $this->assertSame(2, $variant->getEffectiveReservedForOrganizers());

        $info = $capacityManager->getCapacityInfo($variant);
        $this->assertSame(2, $info['reserved']);
    }

    // ==================== 3. CartService with real DB ====================

    public function testCartServiceAddAndRemoveItem(): void
    {
        $cartService = $this->createCartService();
        $user = $this->em->find(User::class, 89901);
        $this->assertNotNull($user);
        $variant = $this->em->find(ProductVariant::class, self::$variantM->getId());
        $this->assertNotNull($variant);

        $initialStock = (int) $this->connection->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => self::$variantM->getId(),
            ],
        );

        // Create cart
        $cart = $cartService->getOrCreateCart($user);
        $this->assertTrue($cart->isPending());

        // Add item
        $item = $cartService->addItem($cart, $variant);
        $this->assertNotNull($item->getId());
        $this->assertSame('Tričko modré', $item->getProductName());
        $this->assertSame('M', $item->getVariantName());

        // Stock decremented
        $stockAfterAdd = (int) $this->connection->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => self::$variantM->getId(),
            ],
        );
        $this->assertSame($initialStock - 1, $stockAfterAdd);
        $this->assertSame('250.00', $cart->getTotalPrice());

        // Remove item
        $cartService->removeItem($cart, $item);

        $stockAfterRemove = (int) $this->connection->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => self::$variantM->getId(),
            ],
        );
        $this->assertSame($initialStock, $stockAfterRemove);
        $this->assertTrue($cart->isEmpty());
    }

    // ==================== 4. ProductBundle with RoleMeaning ====================

    public function testProductBundleAppliesToRoleMeaning(): void
    {
        $bundle = new ProductBundle();
        $bundle->setName('Víkendový balíček');
        $bundle->setForced(true);
        $bundle->setApplicableToRoles([RoleMeaning::PRIHLASEN->value]);

        $this->assertTrue($bundle->appliesToRole(RoleMeaning::PRIHLASEN));
        $this->assertTrue($bundle->isMandatoryForUser([RoleMeaning::PRIHLASEN]));
        $this->assertFalse($bundle->appliesToRole(RoleMeaning::ORGANIZATOR_ZDARMA));

        $bundle->setForced(false);
        $this->assertFalse($bundle->isMandatoryForUser([RoleMeaning::PRIHLASEN]));
    }

    // ==================== 5. UserRoleService with real DB ====================

    public function testUserRoleServiceAssignAndRemoveWithLogging(): void
    {
        $service = $this->createUserRoleService();
        $user = $this->em->find(User::class, 89901);
        $editor = $this->em->find(User::class, 89902);
        $this->assertNotNull($user);
        $this->assertNotNull($editor);

        /** @var Role|null $role */
        $role = $this->em->getRepository(Role::class)->findOneBy([
            'vyznamRole' => RoleMeaning::HERMAN,
            'rocnikRole' => ROCNIK,
        ]);
        if ($role === null) {
            $this->markTestSkipped('No HERMAN role for current year');
        }

        // Assign
        $this->assertTrue($service->assignRole($user, $role, $editor));

        // Verify in DB
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = 89901 AND id_role = :roleId',
            [
                'roleId' => $role->getId(),
            ],
        );
        $this->assertSame(1, $count);

        // Log entry exists
        $logCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM uzivatele_role_log
            WHERE id_uzivatele = 89901 AND id_role = :roleId AND zmena = 'posazen'",
            [
                'roleId' => $role->getId(),
            ],
        );
        $this->assertSame(1, $logCount);

        // Assign again → false
        $this->em->clear();
        $user = $this->em->find(User::class, 89901);
        $role = $this->em->find(Role::class, $role->getId());
        $editor = $this->em->find(User::class, 89902);
        $this->assertFalse($service->assignRole($user, $role, $editor));

        // Remove
        $this->assertTrue($service->removeRole($user, $role, $editor));

        // Removal log
        $removeLogCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM uzivatele_role_log
            WHERE id_uzivatele = 89901 AND id_role = :roleId AND zmena = 'sesazen'",
            [
                'roleId' => $role->getId(),
            ],
        );
        $this->assertSame(1, $removeLogCount);
    }

    // ==================== 6. RoleHistoryRecalculator ====================

    public function testRoleHistoryRecalculator(): void
    {
        $recalculator = new RoleHistoryRecalculator($this->connection);

        // Just verify it runs without error for current year
        $recalculator->recalculate(ROCNIK, 89901);

        $this->assertTrue(true);
    }

    // ==================== Helpers ====================

    private function createCartService(): CartService
    {
        /** @var OrderRepository $orderRepo */
        $orderRepo = $this->em->getRepository(Order::class);
        /** @var ProductBundleRepository $bundleRepo */
        $bundleRepo = $this->em->getRepository(ProductBundle::class);
        $capacityManager = new CapacityManager($this->connection, $this->em);
        $discountCalculator = self::getContainer()->get(DiscountCalculator::class);
        $yearProvider = new CurrentYearProvider();

        return new CartService(
            $this->em,
            $orderRepo,
            $bundleRepo,
            $capacityManager,
            $discountCalculator,
            $yearProvider,
        );
    }

    private function createUserRoleService(): UserRoleService
    {
        $userRoleRepo = $this->em->getRepository(UserRole::class);
        $validator = self::getContainer()->get('validator');
        $yearProvider = new CurrentYearProvider();
        $recalculator = new RoleHistoryRecalculator($this->connection);

        return new UserRoleService(
            $this->em,
            $userRoleRepo,
            $validator,
            $yearProvider,
            $recalculator,
            new \Psr\Log\NullLogger(),
        );
    }
}
