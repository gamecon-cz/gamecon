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
use App\State\Cart\MerchProductsProvider;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Produkt s víc variantami je v datech běžný — ponožky mají dvě velikosti a v roce 2026
 * se prodávají. Mřížka proto nesmí ukazovat jen jednu z nich.
 */
class MerchProductsProviderTest extends TestCase
{
    private const ROK = 2026;

    private MockObject $productRepository;

    private MockObject $orderItemRepository;

    private MockObject $security;

    private MerchProductsProvider $provider;

    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected function setUp(): void
    {
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;

        // Testovací bootstrap tyhle konstanty nedefinuje, ale
        // `prodejPredmetuBezTricekUkoncen()` je čte natvrdo.
        $vychozi = SystemoveNastaveni::zGlobals();
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $vychozi->dejVychoziHodnotu($klic));
        }

        // Termín prodeje merche je uprostřed ročníku, takže „teď" musí na jeho začátek —
        // jinak by se všechno nabízelo jako po termínu.
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: self::ROK,
            ted: new DateTimeImmutableStrict(self::ROK . '-01-01 00:00:00'),
        );

        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->orderItemRepository = $this->createMock(OrderItemRepository::class);
        $this->orderItemRepository->method('countCustomerPurchases')->willReturn(0);
        $this->security = $this->createMock(Security::class);
        $this->security->method('getUser')->willReturn($this->createMock(User::class));

        $discountCalculator = $this->createMock(DiscountCalculator::class);
        $discountCalculator->method('calculateDiscount')->willReturn([
            'finalPrice'     => '100.00',
            'discount'       => null,
            'discountAmount' => null,
            'reason'         => null,
        ]);

        $currentYearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $currentYearProvider->method('getCurrentYear')->willReturn(self::ROK);

        $this->provider = new MerchProductsProvider(
            $this->productRepository,
            $this->orderItemRepository,
            $discountCalculator,
            $currentYearProvider,
            new ProductVariantsForGrid($this->orderItemRepository),
            $this->security,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;
    }

    /**
     * @param array<string, int|null> $variantyAZasoba název velikosti => zbývající kusy
     */
    private function pripravProdukt(array $variantyAZasoba): Product
    {
        $tag = new ProductTag();
        $tag->setCode(ProductTagCode::PREDMET->value);
        $tag->setName('Předmět');

        $product = new Product();
        $product->setName('Ponožky');
        $product->setCode('ponozky_2026');
        $product->setCurrentPrice('100.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->addTag($tag);

        $id = 500;
        foreach ($variantyAZasoba as $nazev => $zasoba) {
            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setName($nazev);
            $variant->setCode('ponozky_2026_' . $nazev);
            $variant->setRemainingQuantity($zasoba);
            // Reálná data mají u obou velikostí position = 0. Pořadí řeší až tiebreak
            // podle `id` v mapování; v paměti ho ArrayCollection stejně neaplikuje, takže
            // tenhle test ověřuje jen to, že se nabídnou obě velikosti.
            $variant->setPosition(0);
            // Varianta bez ID by z nabídky vypadla; v databázi ho má vždy.
            $reflexe = new \ReflectionProperty(ProductVariant::class, 'id');
            $reflexe->setValue($variant, $id++);
            $product->addVariant($variant);
        }

        $this->productRepository->method('findByTag')->willReturn([$product]);

        return $product;
    }

    /**
     * @test
     */
    public function produktSViceVelikostmiNabidneVsechny(): void
    {
        $this->pripravProdukt([
            '38-39' => 8,
            '42-45' => 45,
        ]);

        $merch = $this->provider->provide(new Get());

        self::assertCount(1, $merch, 'Velikosti patří pod jeden produkt, ne do samostatných řádků');
        self::assertCount(
            2,
            $merch[0]->variants,
            'Mřížka musí nabídnout obě velikosti — jinak tu druhou nikdo nekoupí',
        );
    }

    /**
     * Zásoba se liší velikost od velikosti, takže strop musí být per varianta. Dřív se
     * hlásila zásoba jedné a nakupovalo se do druhé.
     *
     * @test
     */
    public function kazdaVelikostMaVlastniStrop(): void
    {
        $this->pripravProdukt([
            '38-39' => 8,
            '42-45' => 45,
        ]);

        $merch = $this->provider->provide(new Get());

        $stropy = [];
        foreach ($merch[0]->variants as $varianta) {
            $stropy[$varianta->name] = $varianta->maxQuantity;
        }

        self::assertSame(8, $stropy['38-39']);
        self::assertSame(45, $stropy['42-45']);
    }

    /**
     * Mřížka klíčuje řádek podle kódu produktu — z varianty ho odvodit nejde, jejich
     * pořadí není napevno dané.
     *
     * @test
     */
    public function produktNeseSvujKod(): void
    {
        $this->pripravProdukt([
            '38-39' => 8,
        ]);

        $merch = $this->provider->provide(new Get());

        self::assertSame('ponozky_2026', $merch[0]->code);
    }

    /**
     * @test
     */
    public function produktSJednouVariantouMaJednuVariantu(): void
    {
        $this->pripravProdukt([
            'jedna velikost' => 10,
        ]);

        $merch = $this->provider->provide(new Get());

        self::assertCount(1, $merch[0]->variants);
        self::assertSame('jedna velikost', $merch[0]->variants[0]->name);
    }
}
