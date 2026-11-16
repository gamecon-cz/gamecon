<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Get;
use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRoleRepository;
use App\Service\AccommodationAvailability;
use App\Service\AccommodationRules;
use App\Service\BreakfastCanceller;
use App\Service\CartService;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\LegacySessionService;
use App\Service\UserPermissions;
use App\State\Cart\AccommodationProvider;
use App\Tests\Support\ChybovePreklady;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
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

    private MockObject $accommodationRules;

    private bool $sleepingBagsOnly = false;

    private bool $maySingleNight = false;

    private bool $isOrganizer = false;

    private bool $operatorMaySingleNight = false;

    private bool $operatorMayOverbook = false;

    private bool $operatorOrdersSunday = false;

    private bool $operatorOnlySeesSunday = false;

    private ?string $discountReason = null;

    private ?User $signedInUser = null;

    private ?User $operator = null;

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
        $discountCalculator->method('calculateDiscount')->willReturnCallback(fn (): array => [
            'finalPrice'     => '1300.00',
            'discount'       => null,
            'discountAmount' => null,
            'reason'         => $this->discountReason,
        ]);

        $currentYearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $currentYearProvider->method('getCurrentYear')->willReturn(self::ROK);

        $this->cartService = $this->createMock(CartService::class);
        $this->breakfastCanceller = $this->createMock(BreakfastCanceller::class);
        $this->breakfastCanceller->method('restorable')->willReturn([]);
        $this->accommodationRules = $this->createMock(AccommodationRules::class);
        $this->accommodationRules->method('sleepingBagsOnly')
            ->willReturnCallback(fn (User $user, int $year): bool => $this->sleepingBagsOnly
                && $user === $this->signedInUser
                && $year === self::ROK);
        // Answers for one right only, about the signed-in user: a blanket stub would pass even
        // if the grid asked about some other permission or some other person.
        $userPermissions = $this->createMock(UserPermissions::class);
        $userPermissions->method('has')->willReturnCallback(
            fn (User $user, int $permission, int $year): bool => $year === self::ROK
                && (
                    ($user === $this->signedInUser && $this->maySingleNight && $permission === Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC)
                    || ($user === $this->operator && $this->operatorMaySingleNight && $permission === Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC)
                    || ($user === $this->operator && $this->operatorMayOverbook && $permission === Pravo::MUZE_PRETIZIT_UBYTOVANI)
                ),
        );
        // The desk worker's own role decides about Sunday, so the answer depends on who orders.
        $this->accommodationRules->method('maySundayNight')->willReturnCallback(
            fn (User $customer, int $year, ?User $orderer = null): bool => $orderer !== null
                && $orderer === $this->operator
                && $this->operatorOrdersSunday,
        );
        $this->accommodationRules->method('isInfopultWorker')->willReturnCallback(
            fn (?User $orderer, int $year): bool => $orderer !== null
                && $orderer === $this->operator
                && $this->operatorOnlySeesSunday,
        );
        $userRoleRepository = $this->createMock(UserRoleRepository::class);
        $userRoleRepository->method('isOrganizer')->willReturnCallback(
            fn (User $user, int $year): bool => $this->isOrganizer
                && $user === $this->signedInUser
                && $year === self::ROK,
        );

        $this->provider = new AccommodationProvider(
            $this->productRepository,
            $this->orderItemRepository,
            $discountCalculator,
            $currentYearProvider,
            $this->legacySession,
            $this->cartService,
            $this->breakfastCanceller,
            $this->accommodationRules,
            $userPermissions,
            $userRoleRepository,
            $this->security,
            // Staví se nad týmiž mocky repozitářů jako provider, aby test procházel
            // skutečným výpočtem, ne nastrčenou odpovědí.
            new AccommodationAvailability($this->productRepository, $this->orderItemRepository),
            ChybovePreklady::translator(),
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;
    }

    public function testSaleClosedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->posunCas('2099-01-01 00:00:00');

        $this->assertTrue($this->provider->provide(new Get())->saleClosed);
    }

    public function testFreeNightIsLockedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->posunCas('2099-01-01 00:00:00');

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertTrue($cell->locked);
        $this->assertFalse($cell->soldOut);
    }

    /**
     * Doobjednat ubytování po termínu je hlavní důvod, proč admin obrazovky existují —
     * zapisovač to schválně nekontroluje. Mřížka pultu to proto nesmí zamknout, jinak
     * objednávku prostě nejde odeslat. Legacy pult to po termínu umí.
     */
    public function testDeskIsNotLockedOutAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->posunCas('2099-01-01 00:00:00');

        $dto = $this->provider->forCustomer(
            $this->security->getUser(),
            $this->legacySession->getCurrentUser(),
            zPultu: true,
        );
        $cell = $dto->types[0]->nights[self::DEN_CTVRTEK];

        self::assertFalse($cell->locked, 'Pult musí po termínu objednat');
        self::assertFalse($dto->saleClosed, 'A nesmí tvrdit, že je zavřeno');
    }

    public function testOwnBookedNightStaysUnlockedAfterTheDeadline(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 1, koupeno: true);
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

        // The roommate and the decline are read off the legacy user, so without one the grid
        // would silently show neither behind a 200.
        $this->expectException(AccessDeniedHttpException::class);

        $this->provider->provide(new Get());
    }

    /**
     * Rezervace drží část postelí stranou: účastník je nevidí, organizátor ano. Merch to
     * umí přes `CapacityManager`, ubytování jde jinou cestou a dosud to míjelo — vyplněná
     * rezervace u noci neznamenala nic a postele se rozprodaly do posledního kusu.
     */
    public function testReservedBedsAreHiddenFromAParticipant(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 3, rezervovanoProOrgy: 5);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(2, $cell->remaining, 'Účastník vidí 10 − 3 prodaných − 5 odložených');
        $this->assertSame(5, $cell->reservedForOrganizers, 'Obsluha musí vidět, kolik je stranou');
    }

    /**
     * Když veřejná část dojde, účastníkovi je vyprodáno, i když postele fyzicky jsou —
     * drží se pro orgy.
     */
    public function testParticipantIsSoldOutOnceOnlyTheReserveIsLeft(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 5, rezervovanoProOrgy: 5);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
        $this->assertSame(5, $cell->reservedForOrganizers);
    }

    /**
     * Bez rezervace se nesmí nic ubrat — a `reservedForOrganizers` má být null, aby
     * mřížka neukazovala „(+0 org)" u každé noci.
     */
    public function testNightWithoutReservationIsUnaffected(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 3);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(7, $cell->remaining);
        $this->assertNull($cell->reservedForOrganizers);
    }

    /**
     * Překlep obsluhy (rezervováno víc, než je vyrobeno) nesmí dát záporné číslo ani
     * spadnout — účastníkovi prostě nezbývá nic.
     */
    public function testReservationLargerThanCapacityLeavesNothingPublic(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 2, sold: 0, rezervovanoProOrgy: 5);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
    }

    /**
     * Organizátor rezervaci neodečítá — vidí celou kapacitu, protože na odložené postele
     * dosáhne. To je druhá půlka pravidla; bez ní by se mu noc jevila jako vyprodaná.
     */
    public function testOrganizerSeesTheReservedBeds(): void
    {
        $this->prepareUser(jeOrganizator: true);
        $this->prepareGrid(produced: 10, sold: 5, rezervovanoProOrgy: 5);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(5, $cell->remaining, 'Organizátorovi se rezervace neodečítá');
        $this->assertFalse($cell->soldOut);
        // Rezerva už je uvnitř `remaining`; kdyby se poslala i zvlášť, pult by u téhle
        // noci četl „zbývá 5 (+5 org)" a myslel si, že postelí je deset.
        $this->assertNull($cell->reservedForOrganizers, 'Nesmí se počítat dvakrát');
    }

    public function testFullyBookedNightIsSoldOut(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 10);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
    }

    public function testOwnBookedLastBedLeavesNothingForOthersButIsNotSoldOut(): void
    {
        $this->prepareUser();

        // The last bed, taken by this very customer: the count is what is left for the others,
        // but under its own ticked checkbox the tile must not read "vyprodáno".
        $this->prepareGrid(produced: 10, sold: 10, koupeno: true);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->selected);
        $this->assertFalse($cell->soldOut);
        $this->assertFalse($cell->locked);
    }

    public function testUnlimitedNightHasNoRemainingCount(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: null, sold: 4);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertNull($cell->remaining);
        $this->assertFalse($cell->soldOut);
    }

    public function testOverbookedNightDoesNotReportNegativeRemaining(): void
    {
        $this->prepareUser();

        // Both write paths feed shop_nakupy and capacity is admin-editable, so sold
        // can exceed produced. The grid must not offer "-2 beds".
        $this->prepareGrid(produced: 10, sold: 12);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertSame(0, $cell->remaining);
        $this->assertTrue($cell->soldOut);
    }

    /**
     * The infopult worker's right to book one night counts for the customer, as it did for the
     * legacy form, which asked the occupant and the orderer.
     */
    public function testTheDeskWorkersRightToASingleNightLowersTheMinimum(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->prepareOperator(maySingleNight: true);

        self::assertSame(1, $this->deskGrid()->minimumNights);
    }

    public function testADeskWorkerWithoutTheRightLeavesTheMinimumAlone(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->prepareOperator();

        self::assertSame(2, $this->deskGrid()->minimumNights);
    }

    public function testAFullNightStaysLockedForAnOrdinaryDeskWorker(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 10);
        $this->prepareOperator();

        $cell = $this->deskGrid()->types[0]->nights[self::DEN_CTVRTEK];

        self::assertTrue($cell->soldOut);
        self::assertTrue($cell->locked);
    }

    public function testAFullNightIsOpenToTheDeskWorkerWhoMayOverbook(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 10);
        $this->prepareOperator(mayOverbook: true);

        $cell = $this->deskGrid()->types[0]->nights[self::DEN_CTVRTEK];

        self::assertTrue($cell->soldOut, 'Pořád je plná, jen se dá přeplnit');
        self::assertFalse($cell->locked);
    }

    /**
     * The grid shows the desk how far over the capacity a night went. The participant never
     * sees it (testOverbookedNightDoesNotReportNegativeRemaining).
     */
    public function testAnOverbookedNightShowsTheNegativeNumberToTheDesk(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 12);
        $this->prepareOperator();

        self::assertSame(-2, $this->deskGrid()->types[0]->nights[self::DEN_CTVRTEK]->remaining);
    }

    public function testTheReserveForOrganizersNeverPushesTheDeskBelowZero(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 2, sold: 0, rezervovanoProOrgy: 5);
        $this->prepareOperator();

        self::assertSame(0, $this->deskGrid()->types[0]->nights[self::DEN_CTVRTEK]->remaining);
    }

    public function testAnOverbookedNightOfTheCustomerIsNotSoldOutForThem(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 11, koupeno: true);
        $this->prepareOperator();

        $cell = $this->deskGrid()->types[0]->nights[self::DEN_CTVRTEK];

        self::assertSame(-1, $cell->remaining);
        self::assertTrue($cell->selected);
        self::assertFalse($cell->soldOut);
        self::assertFalse($cell->locked);
    }

    public function testAParticipantIsNeverOfferedOverbooking(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 10);
        $this->prepareOperator(mayOverbook: true);

        self::assertTrue($this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK]->locked);
    }

    /**
     * Legacy opened the Sunday night to whoever orders it for someone else when that person is an
     * organizer: the customer's own right is not the only way to it.
     */
    public function testAnOrganizerAtTheDeskCanOrderSundayForTheCustomer(): void
    {
        $this->prepareUser();
        $this->sundayOnly();
        $this->prepareOperator(ordersSunday: true);

        $nedele = $this->deskGrid()->types[0]->nights[AccommodationRules::SUNDAY];

        self::assertFalse($nedele->locked);
    }

    /**
     * An infopult worker who is no organizer sees the column and cannot fill it, as in legacy.
     */
    public function testAnInfopultWorkerSeesSundayButCannotOrderIt(): void
    {
        $this->prepareUser();
        $this->sundayOnly();
        $this->prepareOperator(onlySeesSunday: true);

        $dto = $this->deskGrid();

        self::assertContains(AccommodationRules::SUNDAY, array_column($dto->days, 'day'));
        self::assertTrue($dto->types[0]->nights[AccommodationRules::SUNDAY]->locked);
    }

    public function testSundayStaysHiddenFromACustomerAndAnOrdinaryDeskWorker(): void
    {
        $this->prepareUser();
        $this->sundayOnly();
        $this->prepareOperator();

        self::assertNotContains(AccommodationRules::SUNDAY, array_column($this->deskGrid()->days, 'day'));
    }

    public function testVariantWithoutLegacyRowCountsAsUnlimited(): void
    {
        $this->prepareUser();

        // Sold is keyed by variant id but produced by variant code, so a variant
        // whose code has no shop_predmety row falls back to unlimited rather than to zero.
        $this->prepareGrid(produced: null, sold: 2);

        $cell = $this->provider->provide(new Get())->types[0]->nights[self::DEN_CTVRTEK];

        $this->assertNull($cell->remaining);
    }

    public function testRoomTypeCarriesTheReasonOfItsDiscount(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->discountReason = 'Ubytování zdarma';

        $this->assertSame('Ubytování zdarma', $this->provider->provide(new Get())->types[0]->discountReason);
    }

    public function testRoomTypeWithoutDiscountHasNoReason(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);

        $this->assertNull($this->provider->provide(new Get())->types[0]->discountReason);
    }

    public function testVariantlessProductIsNotARow(): void
    {
        $this->prepareUser();

        // The nights absorbed by the day-variant migration stay products for the legacy
        // form to read, but carry no variants and must not become rows of their own.
        $product = $this->createProduct(1, 'Hotel');
        $this->productRepository->method('findByTag')->willReturn([$product]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([]);
        $this->productRepository->method('capacityByVariantCode')->willReturn([]);
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn([]);

        $this->assertSame([], $this->provider->provide(new Get())->types);
    }

    private function prepareOperator(
        bool $maySingleNight = false,
        bool $mayOverbook = false,
        bool $ordersSunday = false,
        bool $onlySeesSunday = false,
    ): void {
        $this->operator = $this->createMock(User::class);
        $this->operatorMaySingleNight = $maySingleNight;
        $this->operatorMayOverbook = $mayOverbook;
        $this->operatorOrdersSunday = $ordersSunday;
        $this->operatorOnlySeesSunday = $onlySeesSunday;
    }

    private function deskGrid(): AccommodationOutputDto
    {
        return $this->provider->forCustomer(
            $this->security->getUser(),
            $this->legacySession->getCurrentUser(),
            zPultu: true,
            orderer: $this->operator,
        );
    }

    /**
     * One room type offering nothing but its Sunday night, wired through the lookups the grid reads.
     */
    private function sundayOnly(): void
    {
        $nedele = $this->nedele(1, 'Kolej', 'kolej-ne', 70);
        $this->productRepository->method('findByTag')->willReturn([$nedele->getProduct()]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([]);
        $this->productRepository->method('capacityByVariantCode')->willReturn([
            'kolej-ne' => [
                'vyrobeno'    => 10,
                'nabizeno'    => true,
                'rezervovano' => null,
            ],
        ]);
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn([]);
    }

    private function posunCas(string $cas): void
    {
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            \ROCNIK,
            new DateTimeImmutableStrict($cas),
        );
    }

    private function prepareUser(bool $smiJednuNoc = false, bool $jeOrganizator = false): void
    {
        $this->maySingleNight = $smiJednuNoc;
        $this->isOrganizer = $jeOrganizator;
        $user = $this->createMock(User::class);
        $this->signedInUser = $user;
        $this->security->method('getUser')->willReturn($user);

        $legacyUzivatel = $this->createMock(\Uzivatel::class);
        $legacyUzivatel->method('ubytovanS')->willReturn('');
        $legacyUzivatel->method('nechceUbytovani')->willReturn(false);

        $this->legacySession->method('getCurrentUser')->willReturn($legacyUzivatel);
    }

    /**
     * One room type with a single Thursday night, wired through every lookup the
     * remaining-count arithmetic reads.
     */
    private function prepareGrid(
        ?int $produced,
        int $sold,
        ?string $kodJinehoRadku = null,
        bool $koupeno = false,
        ?Product $dalsiProdukt = null,
        ?int $rezervovanoProOrgy = null,
    ): ProductVariant {
        $product = $this->createProduct(1, 'Hotel');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setCode('Hd-2L-ct');
        $variant->setName('čtvrtek');
        $variant->setAccommodationDay(self::DEN_CTVRTEK);
        $this->setId($variant, self::ID_VARIANTY);
        $product->addVariant($variant);

        $this->productRepository->method('findByTag')
            ->with(ProductTagCode::UBYTOVANI)
            ->willReturn($dalsiProdukt === null ? [$product] : [$product, $dalsiProdukt]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([
            50 => $sold,
        ]);
        $this->productRepository->method('capacityByVariantCode')
            ->willReturn([
                ($kodJinehoRadku ?? 'Hd-2L-ct') => [
                    'vyrobeno'    => $produced,
                    'nabizeno'    => true,
                    'rezervovano' => $rezervovanoProOrgy,
                ],
                'spacak-ct' => [
                    'vyrobeno'    => 10,
                    'nabizeno'    => true,
                    'rezervovano' => null,
                ],
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
            $koupeneItems[] = $item;
        }
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn($koupeneItems);

        return $variant;
    }

    /**
     * A held Sunday shows the Sunday column to someone without the right (an import, a revoked
     * right), but only so they can drop it: the other room types' Sundays stay unoffered.
     */
    public function testAHeldSundayDoesNotOfferTheOtherSundays(): void
    {
        $this->prepareUser();
        $drzena = $this->nedele(1, 'Kolej', 'kolej-ne', 70);
        $jina = $this->nedele(2, 'Hotel', 'hotel-ne', 71);
        $this->productRepository->method('findByTag')->willReturn([$drzena->getProduct(), $jina->getProduct()]);
        $this->orderItemRepository->method('countSoldByVariant')->willReturn([]);
        $this->productRepository->method('capacityByVariantCode')->willReturn([
            'kolej-ne' => [
                'vyrobeno'    => 10,
                'nabizeno'    => true,
                'rezervovano' => null,
            ],
            'hotel-ne' => [
                'vyrobeno'    => 10,
                'nabizeno'    => true,
                'rezervovano' => null,
            ],
        ]);
        $drzenyNakup = new OrderItem();
        $drzenyNakup->setVariant($drzena);
        $this->orderItemRepository->method('findByCustomerAndYear')->willReturn([$drzenyNakup]);

        $dto = $this->provider->forCustomer($this->security->getUser(), $this->legacySession->getCurrentUser(), zPultu: true);

        $nedele = [];
        foreach ($dto->types as $typ) {
            $nedele[$typ->productId] = $typ->nights[AccommodationRules::SUNDAY];
        }
        self::assertFalse($nedele[1]->locked, 'Drženou neděli musí jít odškrtnout');
        self::assertTrue($nedele[2]->locked, 'Bez práva na neděli se jiná neděle nenabízí');
    }

    private function nedele(int $idProduktu, string $nazev, string $kod, int $idVarianty): ProductVariant
    {
        $typPokoje = $this->createProduct($idProduktu, $nazev);
        $typPokoje->addTag((new ProductTag())->setCode(ProductTagCode::UBYTOVANI->value));
        $nedele = new ProductVariant();
        $nedele->setProduct($typPokoje);
        $nedele->setCode($kod);
        $nedele->setName('neděle');
        $nedele->setAccommodationDay(AccommodationRules::SUNDAY);
        $this->setId($nedele, $idVarianty);
        $typPokoje->addVariant($nedele);

        return $nedele;
    }

    public function testSleepingBagRestrictionWithNothingTaggedIsRefused(): void
    {
        $this->prepareUser();
        $this->prepareGrid(produced: 10, sold: 0);
        $this->sleepingBagsOnly = true;

        // Hiding everything would read as a broken section rather than a restriction.
        $this->expectExceptionMessage('žádný spacák není v nabídce');

        $this->provider->provide(new Get());
    }

    public function testSleepingBagRestrictionHidesRoomTypes(): void
    {
        $this->prepareUser();
        $spacak = $this->vytvorSpacak();
        $pokoj = $this->prepareGrid(
            produced: 10, sold: 0, dalsiProdukt: $spacak,
        );
        $this->sleepingBagsOnly = true;

        $typy = $this->provider->provide(new Get())->types;

        // Legacy skips a non-sleeping-bag type entirely rather than showing it disabled.
        self::assertCount(1, $typy);
        self::assertSame((int) $spacak->getId(), $typy[0]->productId);
        self::assertNotSame((int) $pokoj->getProduct()->getId(), $typy[0]->productId);
    }

    /**
     * A sleeping-bag product to pass into prepareGrid() as an extra offered type.
     */
    private function vytvorSpacak(): Product
    {
        $spacak = $this->createProduct(2, 'Spacák');
        $tag = new ProductTag();
        $tag->setCode(ProductTagCode::SPACAK->value);
        $spacak->addTag($tag);

        $variant = new ProductVariant();
        $variant->setProduct($spacak);
        $variant->setCode('spacak-ct');
        $variant->setName('čtvrtek');
        $variant->setAccommodationDay(self::DEN_CTVRTEK);
        $this->setId($variant, 60);
        $spacak->addVariant($variant);

        return $spacak;
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

    /**
     * Dvě noci jsou minimum pro běžného účastníka; mřížka podle toho blokuje osamocenou noc.
     *
     * @test
     */
    public function beznyUcastnikMusiObjednatDveNoci(): void
    {
        $this->prepareUser();
        $this->prepareGrid(10, 0);

        self::assertSame(2, $this->provider->provide(new Get())->minimumNights);
    }

    /**
     * @test
     */
    public function pravoNaJednuNocSnizujeMinimum(): void
    {
        $this->prepareUser(smiJednuNoc: true);
        $this->prepareGrid(10, 0);

        self::assertSame(1, $this->provider->provide(new Get())->minimumNights);
    }
}
