<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Get;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\DiscountCalculator;
use App\Service\ProductVariantsForGrid;
use App\Service\RestrictedProductRules;
use App\Service\SpentQuotaProvider;
use App\State\Cart\ShirtProductsProvider;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Svršky mají tři vlastnosti, které merch nemá: vlastní termín prodeje pro trička a jiný
 * pro mikiny, a omezená trička, na která musí mít zákazník právo.
 */
class ShirtProductsProviderTest extends TestCase
{
    private const ROK = 2026;

    private MockObject $productRepository;

    private MockObject $restrictedProductRules;

    private ShirtProductsProvider $provider;

    private ?SystemoveNastaveni $puvodniNastaveni = null;

    private bool $smiOmezene = false;

    protected function setUp(): void
    {
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;

        $vychozi = SystemoveNastaveni::zGlobals();
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $vychozi->dejVychoziHodnotu($klic));
        }

        $this->posunCas(self::ROK . '-01-01 00:00:00');

        $this->productRepository = $this->createMock(ProductRepository::class);

        $orderItemRepository = $this->createMock(OrderItemRepository::class);
        $orderItemRepository->method('countCustomerPurchases')->willReturn(0);

        $discountCalculator = $this->createMock(DiscountCalculator::class);
        $discountCalculator->method('calculateDiscount')->willReturn([
            'finalPrice'     => '200.00',
            'discount'       => null,
            'discountAmount' => null,
            'reason'         => null,
        ]);

        $currentYearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $currentYearProvider->method('getCurrentYear')->willReturn(self::ROK);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->createMock(User::class));

        $this->restrictedProductRules = $this->createMock(RestrictedProductRules::class);
        // Legacy uživatel musí existovat, jinak by ho fail-closed kontrola odmítla dřív,
        // než se vůbec zeptá na právo.
        $this->restrictedProductRules->method('legacyUserFor')
            ->willReturn($this->createMock(\Uzivatel::class));
        $this->restrictedProductRules->method('isRestricted')
            ->willReturnCallback(
                static fn (Product $product): bool => $product->hasTag(ProductTagCode::TRICKO_CERVENE->value)
                    || $product->hasTag(ProductTagCode::TRICKO_MODRE->value),
            );
        $this->restrictedProductRules->method('mayOrder')
            ->willReturnCallback(fn (): bool => $this->smiOmezene);

        $this->provider = new ShirtProductsProvider(
            $this->productRepository,
            $orderItemRepository,
            $discountCalculator,
            $currentYearProvider,
            $this->restrictedProductRules,
            new ProductVariantsForGrid($orderItemRepository),
            $this->createMock(SpentQuotaProvider::class),
            $security,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;
    }

    private function posunCas(string $ted): void
    {
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: self::ROK,
            ted: new DateTimeImmutableStrict($ted),
        );
    }

    private function tag(ProductTagCode $kod): ProductTag
    {
        $tag = new ProductTag();
        $tag->setCode($kod->value);
        $tag->setName($kod->value);

        return $tag;
    }

    private function vytvor(string $nazev, ProductStateEnum $stav, ProductTagCode ...$tagy): Product
    {
        $product = new Product();
        $product->setName($nazev);
        $product->setCode(strtolower($nazev) . '_2026');
        $product->setCurrentPrice('200.00');
        $product->setDescription('');
        $product->setState($stav);
        $product->setProducedQuantity(10);
        foreach ($tagy as $tag) {
            $product->addTag($this->tag($tag));
        }

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('L');
        $variant->setCode($product->getCode() . '-l');
        $variant->setRemainingQuantity(10);
        $variant->setPosition(0);
        // Nenaperzistovaná varianta nemá id a provider ji přeskočí; v databázi ho má vždy.
        (new \ReflectionProperty(ProductVariant::class, 'id'))->setValue($variant, 700);
        $product->addVariant($variant);

        return $product;
    }

    /**
     * @param Product[] $produkty
     */
    private function nabidka(array $produkty): array
    {
        $this->productRepository->method('findByTag')
            ->willReturnCallback(static function (ProductTagCode $tag) use ($produkty): array {
                return array_values(array_filter(
                    $produkty,
                    static fn (Product $p): bool => $p->hasTag($tag->value),
                ));
            });

        return $this->provider->provide(new Get());
    }

    /**
     * @test
     */
    public function nabidneTrickaIMikiny(): void
    {
        $svrsky = $this->nabidka([
            $this->vytvor('Tricko', ProductStateEnum::PUBLIC, ProductTagCode::TRICKO),
            $this->vytvor('Mikina', ProductStateEnum::PUBLIC, ProductTagCode::PREDMET, ProductTagCode::MIKINA),
        ]);

        self::assertCount(2, $svrsky, 'Mikiny se prodávají spolu s tričky, ne v merchi');
    }

    /**
     * Bez práva nesmí být omezené tričko ani vidět — jinak zákazník klikne a dostane chybu
     * místo toho, aby se mu vůbec nenabídlo.
     *
     * @test
     */
    public function omezeneTrickoBezPravaNeniVNabidce(): void
    {
        $this->smiOmezene = false;

        $svrsky = $this->nabidka([
            $this->vytvor('Cervene', ProductStateEnum::RESTRICTED, ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE),
        ]);

        self::assertSame([], $svrsky);
    }

    /**
     * @test
     */
    public function omezeneTrickoSPravemVNabidceJe(): void
    {
        $this->smiOmezene = true;

        $svrsky = $this->nabidka([
            $this->vytvor('Cervene', ProductStateEnum::RESTRICTED, ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE),
        ]);

        self::assertCount(1, $svrsky);
        self::assertTrue($svrsky[0]->available, 'Kdo na omezené tričko má právo, musí si ho moct koupit');
    }

    /**
     * Trička a mikiny mají každé svůj termín. Testují se zvlášť, protože termíny jsou PHP
     * konstanty — v procesu je definuje první test, který je potřebuje, a přenastavit je
     * nejde. Test na jejich vzájemné pořadí by tedy závisel na tom, co běželo předtím.
     *
     * @test
     */
    public function poSvemTerminuSeTrickoNeprodava(): void
    {
        $this->posunCas(
            SystemoveNastaveni::zGlobals()->prodejTricekDo()->modifyStrict('+1 day')->format('Y-m-d H:i:s'),
        );

        $svrsky = $this->nabidka([
            $this->vytvor('Tricko', ProductStateEnum::PUBLIC, ProductTagCode::TRICKO),
        ]);

        self::assertSame([], $svrsky, 'Po termínu triček se tričko nenabízí');
    }

    /**
     * @test
     */
    public function poSvemTerminuSeMikinaNeprodava(): void
    {
        $this->posunCas(
            SystemoveNastaveni::zGlobals()->prodejMikinDo()->modifyStrict('+1 day')->format('Y-m-d H:i:s'),
        );

        $svrsky = $this->nabidka([
            $this->vytvor('Mikina', ProductStateEnum::PUBLIC, ProductTagCode::PREDMET, ProductTagCode::MIKINA),
        ]);

        self::assertSame([], $svrsky, 'Po termínu mikin se mikina nenabízí');
    }

    /**
     * Mikina nesmí viset na termínu triček — jsou to dvě nezávislá nastavení a provider
     * se musí ptát toho správného.
     *
     * @test
     */
    public function mikinaNevisiNaTerminuTricek(): void
    {
        $nastaveni = SystemoveNastaveni::zGlobals();
        $terminTricek = $nastaveni->prodejTricekDo();
        $terminMikin = $nastaveni->prodejMikinDo();
        if ($terminMikin <= $terminTricek) {
            self::markTestSkipped('Mikiny letos končí dřív než trička, tohle by neměřilo nic');
        }

        $this->posunCas($terminTricek->modifyStrict('+1 day')->format('Y-m-d H:i:s'));

        $svrsky = $this->nabidka([
            $this->vytvor('Mikina', ProductStateEnum::PUBLIC, ProductTagCode::PREDMET, ProductTagCode::MIKINA),
        ]);

        self::assertCount(1, $svrsky, 'Mikina má vlastní, pozdější termín');
    }
}
