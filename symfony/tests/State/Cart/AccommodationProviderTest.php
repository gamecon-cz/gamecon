<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Get;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\BreakfastCanceller;
use App\Service\CartService;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\LegacySessionService;
use App\State\Cart\AccommodationProvider;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccommodationProviderTest extends TestCase
{
    private const ROK = 2026;

    private const DEN_CTVRTEK = 1;

    private const ID_VARIANTY = 50;

    private MockObject $productRepository;

    private MockObject $orderItemRepository;

    private MockObject $legacySession;

    private MockObject $security;

    private MockObject $cartService;

    private MockObject $breakfastCanceller;

    private AccommodationProvider $provider;

    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected function setUp(): void
    {
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;

        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->legacySession = $this->createMock(LegacySessionService::class);
        $this->security = $this->createMock(Security::class);

        $discountCalculator = $this->createMock(DiscountCalculator::class);
        $discountCalculator->method('calculateDiscount')->willReturn([
            'finalPrice'     => '1300.00',
            'discount'       => null,
            'discountAmount' => null,
            'reason'         => null,
        ]);

        $currentYearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $currentYearProvider->method('getCurrentYear')->willReturn(self::ROK);

        $this->cartService = $this->createMock(CartService::class);
        $this->breakfastCanceller = $this->createMock(BreakfastCanceller::class);
        $this->breakfastCanceller->method('restorable')->willReturn([]);

        $this->provider = new AccommodationProvider(
            $this->productRepository,
            $this->orderItemRepository,
            $discountCalculator,
            $currentYearProvider,
            $this->legacySession,
            $this->cartService,
            $this->breakfastCanceller,
            $this->security,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;
    }

    public function testSaleClosedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(remainingQuantity: 10, produced: 10, sold: 0, held: 0);
        $this->posunCas('2099-01-01 00:00:00');

        $this->assertTrue($this->provider->provide(new Get())->saleClosed);
    }

    public function testFreeNightIsLockedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(remainingQuantity: 10, produced: 10, sold: 0, held: 0);
        $this->posunCas('2099-01-01 00:00:00');

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertTrue($cell->locked);
        $this->assertFalse($cell->soldOut);
    }

    public function testOwnBookedNightStaysUnlockedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(remainingQuantity: 10, produced: 10, sold: 1, held: 1, koupeno: true);
        $this->posunCas('2099-01-01 00:00:00');

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        // Otherwise the customer could no longer cancel a night they already hold.
        $this->assertTrue($cell->selected);
        $this->assertFalse($cell->locked);
    }

    public function testMissingLegacySessionIsRefused(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->legacySession->method('getCurrentUser')->willReturn(null);

        // Degrading to "no rights" would silently drop an organizer's Sunday night
        // behind a 200, so the endpoint has to refuse instead.
        $this->expectException(AccessDeniedHttpException::class);

        $this->provider->provide(new Get());
    }

    /**
     * The regression this pins: OrderItem maps to shop_nakupy, so the sold count already
     * covers the new cart, whose sales CapacityManager has also taken off
     * remaining_quantity. Deriving from remaining_quantity double-counts them.
     */
    public function testRemainingIsProducedMinusSoldRegardlessOfRemainingQuantity(): void
    {
        $this->prepareUser();

        // remaining_quantity is deliberately absurd: reading it would show 999 free beds.
        $variant = $this->prepareGrid(remainingQuantity: 999, produced: 10, sold: 3, held: 0);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(7, $cell->remaining);
        $this->assertFalse($cell->soldOut);
        $this->assertSame($variant->getId(), $cell->variantId);
    }

    public function testFullyBookedNightIsSoldOut(): void
    {
        $this->prepareUser();
        $this->prepareGrid(remainingQuantity: 10, produced: 10, sold: 10, held: 0);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
    }

    public function testOwnBookedNightIsNotSoldOut(): void
    {
        $this->prepareUser();

        // The last bed, taken by this very customer: legacy adds it back so the row does
        // not render sold out under its own ticked checkbox.
        $this->prepareGrid(remainingQuantity: 0, produced: 10, sold: 10, held: 1);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(1, $cell->remaining);
        $this->assertFalse($cell->soldOut);
    }

    public function testUnlimitedNightHasNoRemainingCount(): void
    {
        $this->prepareUser();
        $this->prepareGrid(remainingQuantity: null, produced: null, sold: 4, held: 0);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertNull($cell->remaining);
        $this->assertFalse($cell->soldOut);
    }

    public function testOverbookedNightDoesNotReportNegativeRemaining(): void
    {
        $this->prepareUser();

        // Both write paths feed shop_nakupy and kusu_vyrobeno is admin-editable, so sold
        // can exceed produced. The grid must not offer "-2 beds".
        $this->prepareGrid(remainingQuantity: 0, produced: 10, sold: 12, held: 0);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
    }

    public function testVariantWithoutLegacyRowCountsAsUnlimited(): void
    {
        $this->prepareUser();

        // Sold and held are keyed by variant id but produced by variant code, so a variant
        // whose code has no shop_predmety row falls back to unlimited rather than to zero.
        $this->prepareGrid(remainingQuantity: 5, produced: null, sold: 2, held: 0);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertNull($cell->remaining);
    }

    public function testVariantlessProductIsNotARow(): void
    {
        $this->prepareUser();

        // The nights absorbed by the day-variant migration stay products for the legacy
        // form to read, but carry no variants and must not become rows of their own.
        $product = $this->createProduct(1, 'Hotel');
        $this->productRepository->method('findByTag')->willReturn([$product]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([]);
        $this->orderItemRepository->method('countHeldByCustomer')->willReturn([]);
        $this->productRepository->method('producedQuantityByVariantCode')->willReturn([]);
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn([]);

        $this->assertSame([], $this->provider->provide(new Get())->types);
    }

    private function posunCas(string $cas): void
    {
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            \ROCNIK,
            new DateTimeImmutableStrict($cas),
        );
    }

    private function prepareUser(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));

        $legacyUzivatel = $this->createMock(\Uzivatel::class);
        $legacyUzivatel->method('maPravo')->willReturn(false);
        $legacyUzivatel->method('jeOrganizator')->willReturn(false);
        $legacyUzivatel->method('ubytovanS')->willReturn('');
        $legacyUzivatel->method('nechceUbytovani')->willReturn(false);

        $this->legacySession->method('getCurrentUser')->willReturn($legacyUzivatel);
    }

    /**
     * One room type with a single Thursday night, wired through every lookup the
     * remaining-count arithmetic reads.
     */
    private function prepareGrid(
        ?int $remainingQuantity,
        ?int $produced,
        int $sold,
        int $held,
        ?string $kodJinehoRadku = null,
        bool $koupeno = false,
    ): ProductVariant {
        $product = $this->createProduct(1, 'Hotel');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setCode('Hd-2L-ct');
        $variant->setName('čtvrtek');
        $variant->setAccommodationDay(self::DEN_CTVRTEK);
        $variant->setRemainingQuantity($remainingQuantity);
        $this->setId($variant, self::ID_VARIANTY);
        $product->addVariant($variant);

        $this->productRepository->method('findByTag')
            ->with(ProductTagCode::UBYTOVANI)
            ->willReturn([$product]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([
            50 => $sold,
        ]);
        $this->orderItemRepository->method('countHeldByCustomer')->willReturn([
            50 => $held,
        ]);
        $this->productRepository->method('producedQuantityByVariantCode')
            ->willReturn([
                ($kodJinehoRadku ?? 'Hd-2L-ct') => $produced,
            ]);
        $koupeneItems = [];
        if ($koupeno) {
            // koupeneNoci() reads the tag off the product, because meals carry
            // accommodation_day too and would otherwise count as booked nights.
            $tag = new ProductTag();
            $tag->setCode(ProductTagCode::UBYTOVANI->value);
            $product->addTag($tag);

            $item = new OrderItem();
            $item->setVariant($variant);
            $item->setProduct($product);
            $koupeneItems[] = $item;
        }
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn($koupeneItems);

        return $variant;
    }

    private function createProduct(int $id, string $name): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setCurrentPrice('1300.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $this->setId($product, $id);

        return $product;
    }

    private function setId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
