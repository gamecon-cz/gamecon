<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Get;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\DiscountCalculator;
use App\Service\LegacySessionService;
use App\State\Cart\MealProductsProvider;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;

/**
 * `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` znamená „objednat **a měnit**": po termínu GameCon
 * nahlásil počty do jídelny, takže účastník nesmí ani přidat, ani zrušit. Legacy to tak
 * dělalo (`Shop::jidloHtml()`), nová vrstva ten termín nečetla vůbec.
 */
class MealProductsProviderTest extends TestCase
{
    private const ROK = 2026;

    private ProductRepository&MockObject $productRepository;

    private Security&MockObject $security;

    private LegacySessionService&MockObject $legacySession;

    private MealProductsProvider $provider;

    private MockClock $clock;

    private mixed $puvodniNastaveni = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;

        // Vlastní `nabizet_do` produktu se porovnává s hodinami, ne se `SystemoveNastaveni`,
        // takže „teď" musí jít nastavit i pro ně.
        $this->clock = new MockClock('2026-01-01 00:00:00');
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->security = $this->createMock(Security::class);

        $discountCalculator = $this->createMock(DiscountCalculator::class);
        $discountCalculator->method('calculateDiscount')->willReturn([
            'discount'       => null,
            'discountAmount' => '0.00',
            'finalPrice'     => '140.00',
            'reason'         => null,
        ]);
        $discountCalculator->method('priceSteps')->willReturn([]);

        $yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $yearProvider->method('getCurrentYear')->willReturn(self::ROK);

        // `CustomerDeskRights` je readonly, takže se nemockuje — staví se nad mocknutou
        // session, což je zároveň bližší tomu, co se testuje (právo operátora).
        $this->legacySession = $this->createMock(LegacySessionService::class);
        $this->provider = new MealProductsProvider(
            $this->productRepository,
            $discountCalculator,
            $yearProvider,
            $this->security,
            new CustomerDeskRights($this->legacySession),
            $this->clock,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;

        parent::tearDown();
    }

    private function pripravJidlo(?string $nabizetDo = null, ?ProductStateEnum $stav = null): void
    {
        $product = new Product();
        $product->setName('Oběd čtvrtek');
        $product->setCode('obed-ct');
        $product->setCurrentPrice('140.00');
        $product->setState($stav ?? ProductStateEnum::PUBLIC);
        $product->setDescription('');
        $product->setAccommodationDay(1);
        if ($nabizetDo !== null) {
            $product->setAvailableUntil(new \DateTimeImmutable($nabizetDo));
        }

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('porce');
        $variant->setCode('obed-ct-p');
        (new \ReflectionProperty(ProductVariant::class, 'id'))->setValue($variant, 1113);
        $product->addVariant($variant);

        $this->productRepository->method('findByTag')
            ->with(ProductTagCode::JIDLO)
            ->willReturn([$product]);
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
    }

    private function posunCas(string $ted): void
    {
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            \ROCNIK,
            new DateTimeImmutableStrict($ted),
        );
        $this->clock->modify($ted);
    }

    /**
     * Bez databáze si `SystemoveNastaveni` termín dopočítá z ročníku, takže přesné datum
     * se tady testovat nedá — ověřuje se rozhodnutí podle `prodejJidlaUkoncen()`, a to
     * s časem hluboko v minulosti, kdy termín ještě nemohl uplynout.
     */
    public function testMealIsNotLockedBeforeTheDeadline(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2000-01-01 00:00:00');

        self::assertFalse($this->provider->provide(new Get())[0]->locked);
    }

    public function testMealIsLockedAfterTheDeadline(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2099-01-01 00:00:00');

        self::assertTrue($this->provider->provide(new Get())[0]->locked);
    }

    /**
     * Tady se jídlo liší od ubytování: u nocí se koupené nezamykají, aby šly odškrtnout.
     * U jídla to nejde — počty už jsou nahlášené v jídelně, takže po termínu nesmí ani
     * zrušit, jinak by se platilo za jídlo, které nikdo nesní.
     */
    public function testEvenAnOrderedMealIsLockedAfterTheDeadline(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2099-01-01 00:00:00');

        $dto = $this->provider->provide(new Get())[0];

        self::assertTrue($dto->locked, 'Ani koupené jídlo nejde po termínu měnit');
    }

    /**
     * Pult po termínu doobjednat smí — proto admin obrazovky existují a proto ani
     * `MealWriter` termín nekontroluje. Pozná se podle `?customerId`, které posílá jen
     * matice v adminu; samotné právo obsluhy ověřuje `CustomerDeskRights`.
     */
    public function testDeskIsNotLockedOutAfterTheDeadline(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2099-01-01 00:00:00');
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturn(true);
        $this->legacySession->method('getCurrentUser')->willReturn($operator);

        $meals = $this->provider->provide(new Get(), [], [
            'filters' => [
                'customerId' => '5246',
            ],
        ]);

        self::assertFalse($meals[0]->locked);
    }

    /**
     * `?customerId` sám o sobě nestačí — bez práva obsluhy by si účastník odemkl matici
     * tím, že si parametr do URL dopíše.
     */
    public function testCustomerIdWithoutTheRightDoesNotUnlock(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2099-01-01 00:00:00');
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturn(false);
        $this->legacySession->method('getCurrentUser')->willReturn($operator);

        $meals = $this->provider->provide(new Get(), [], [
            'filters' => [
                'customerId' => '5246',
            ],
        ]);

        self::assertTrue($meals[0]->locked);
    }

    /**
     * Jídlo má vedle termínu kategorie i vlastní `nabizet_do`, editovatelné ve správě
     * předmětů. Účastníkovi po něm porci nenabízíme.
     *
     * Termín kategorie je schválně hluboko v minulosti, aby zamykat mohlo jedině produktové
     * datum; bez toho by test prošel i s prázdným `nabizet_do` a netvrdil by nic.
     */
    public function testMealPastItsOwnDateIsLockedForCustomer(): void
    {
        $this->pripravJidlo(nabizetDo: '2000-06-01 00:00:00');
        $this->posunCas('2000-06-02 00:00:00');

        self::assertTrue(
            $this->provider->provide(new Get())[0]->locked,
            'Po vlastním nabizet_do se jídlo účastníkovi nabízet nesmí',
        );
    }

    /**
     * Protipól: se stejným „teď" a bez produktového data zůstane jídlo odemčené. Jinak by
     * předchozí test mohl projít kvůli termínu kategorie.
     */
    public function testMealWithoutItsOwnDateStaysOpen(): void
    {
        $this->pripravJidlo();
        $this->posunCas('2000-01-01 00:00:00');

        self::assertFalse(
            $this->provider->provide(new Get())[0]->locked,
            'Bez vlastního termínu nemá jídlo co zamykat',
        );
    }

    /**
     * Propadlé `nabizet_do` je konec samoobsluhy, ne stažení z prodeje — legacy pultu
     * prodej povoluje přes `jidloBezZamku`, které si obě admin obrazovky zapínají.
     */
    public function testDeskSellsAMealPastItsOwnDate(): void
    {
        $this->pripravJidlo(nabizetDo: '2000-06-01 00:00:00');
        $this->posunCas('2000-06-02 00:00:00');
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturn(true);
        $this->legacySession->method('getCurrentUser')->willReturn($operator);

        $meals = $this->provider->provide(new Get(), [], [
            'filters' => [
                'customerId' => '5246',
            ],
        ]);

        self::assertFalse($meals[0]->locked, 'Pult smí doprodat i po produktovém termínu');
    }

    /**
     * Stažený produkt je proti tomu zamčený pro všechny — pult nevyjímaje.
     */
    public function testNobodySellsARetiredMeal(): void
    {
        $this->pripravJidlo(stav: ProductStateEnum::RETIRED);
        $this->posunCas('2000-01-01 00:00:00');
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturn(true);
        $this->legacySession->method('getCurrentUser')->willReturn($operator);

        $meals = $this->provider->provide(new Get(), [], [
            'filters' => [
                'customerId' => '5246',
            ],
        ]);

        self::assertTrue($meals[0]->locked, 'Stažené jídlo neprodá ani pult');
    }
}
