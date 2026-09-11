<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\CapacityManager;
use App\Service\CartService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Stock movement through the cart, against a real database.
 *
 * CartServiceTest covers the same service with a mocked CapacityManager, so it asserts
 * that the guard is called, never that it holds: the capacity check lives in the WHERE
 * clause of CapacityManager's UPDATE, which only real SQL can answer.
 */
class CartServiceStockTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    /**
     * `SystemoveNastaveni::prodejPredmetuBezTricekDo()` dereferences these constants
     * unguarded, and the test bootstrap does not define them — without this every merch
     * purchase dies on "Undefined constant" rather than on the rule being tested.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $systemoveNastaveni->dejVychoziHodnotu($klic));
        }

        // The deadlines above default to mid-year, so with the real "now" every merch
        // purchase is refused as too late. CartService reads the global instance, so
        // moving "now" to the start of the year is what puts the sale back in season.
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-01-01 00:00:00'),
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;

        parent::tearDown();
    }

    private function cartService(): CartService
    {
        return static::getContainer()->get(CartService::class);
    }

    private function vytvorZakaznika(): User
    {
        /** @var User $zakaznik */
        $zakaznik = UserFactory::createOne([
            UserEntityStructure::login => 'kosik_test_' . uniqid(),
            UserEntityStructure::email => 'kosik_test_' . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'Nakupující Testovací',
        ])->_save()->_real();

        return $zakaznik;
    }

    /**
     * product_tag.created_at is NOT NULL without a default and unmapped on the entity, so
     * the row is created in SQL and then read back as a managed entity.
     */
    private function tagPredmet(): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::PREDMET->value,
                'name' => 'Předmět',
            ],
        );

        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => ProductTagCode::PREDMET->value,
            ]);
        self::assertNotNull($tag, 'Tag předmětu se nepodařilo založit');

        return $tag;
    }

    /**
     * A plain merch product with one variant holding the whole stock.
     */
    private function vytvorPredmet(
        ?int $kusuVyrobeno,
        string $cena = '50.00',
        ProductStateEnum $stav = ProductStateEnum::PUBLIC,
    ): ProductVariant {
        $kod = 'merch-' . uniqid();

        $product = new Product();
        $product->setName('Placka');
        $product->setCode($kod);
        $product->setCurrentPrice($cena);
        $product->setDescription('');
        $product->setState($stav);
        $product->setProducedQuantity($kusuVyrobeno);
        // Through the entity, not SQL: CartService asks $product->hasTag(), which reads the
        // mapped collection. A join row inserted behind Doctrine leaves that collection
        // empty, and the merch guard it feeds then never runs.
        $product->addTag($this->tagPredmet());
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('jedna velikost');
        $variant->setCode($kod . '-1');
        $variant->setPrice($cena);
        $variant->setRemainingQuantity($kusuVyrobeno);
        $variant->setPosition(0);
        $product->addVariant($variant);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return $variant;
    }

    private function zbyvajiciKusy(ProductVariant $variant): ?int
    {
        $zbyva = $this->connection()->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => $variant->getId(),
            ],
        );

        return $zbyva === null ? null : (int) $zbyva;
    }

    private function ulozenaCena(OrderItem $polozka): string
    {
        return (string) $this->connection()->fetchOne(
            'SELECT cena_nakupni FROM shop_nakupy WHERE id_nakupu = :id',
            [
                'id' => $polozka->getId(),
            ],
        );
    }

    private function pocetNakupu(User $zakaznik, ProductVariant $variant): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :customer AND variant_id = :variant',
            [
                'customer' => $zakaznik->getId(),
                'variant'  => $variant->getId(),
            ],
        );
    }

    /**
     * @test
     */
    public function objednaneKusySeOdectouZDostupnychZasob(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: 10);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        self::assertSame(10, $this->zbyvajiciKusy($variant), 'Před objednávkou musí být k dispozici všech 10 kusů');

        for ($kus = 0; $kus < 3; ++$kus) {
            $this->cartService()->addItem($kosik, $variant);
        }
        $this->entityManager()->flush();

        self::assertSame(
            7,
            $this->zbyvajiciKusy($variant),
            'Po objednání 3 kusů musí zbývat 7 dostupných',
        );
    }

    /**
     * @test
     */
    public function nakupNadRamecZasobyNeprojde(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: 2);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->cartService()->addItem($kosik, $variant);
        $this->cartService()->addItem($kosik, $variant);
        $this->entityManager()->flush();

        self::assertSame(0, $this->zbyvajiciKusy($variant), 'Dva kusy musí zásobu vyčerpat');

        $chyba = null;
        try {
            $this->cartService()->addItem($kosik, $variant);
        } catch (\RuntimeException $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Nákup přes zbývající zásobu musí skončit chybou');
        self::assertStringContainsString('kapacita', $chyba->getMessage());

        self::assertSame(
            0,
            $this->zbyvajiciKusy($variant),
            'Odmítnutý nákup nesmí zásobu posunout do záporu',
        );
    }

    /**
     * Objednávka většího počtu kusů, než zbývá, nesmí projít ani zčásti.
     *
     * Vede přes CapacityManager: addItem() kupuje vždy po jednom kusu, takže víckusovou
     * cestu z něj není jak vyvolat. Podmínka je přímo ve WHERE toho UPDATE, tedy přesně to,
     * co mockovaný test ověřit nedokáže.
     *
     * @test
     */
    public function vicekusovyNakupNadZasobuNeodebereNic(): void
    {
        $variant = $this->vytvorPredmet(kusuVyrobeno: 2);
        $capacityManager = static::getContainer()->get(CapacityManager::class);

        $chyba = null;
        try {
            $capacityManager->purchase($variant, 3);
        } catch (\RuntimeException $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Nákup 3 kusů při zásobě 2 musí skončit chybou');
        self::assertSame(
            2,
            $this->zbyvajiciKusy($variant),
            'Odmítnutý víckusový nákup nesmí odebrat ani jeden kus',
        );
    }

    /**
     * @test
     */
    public function vyprodanyPredmetNejdeKoupit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: 0);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        // The message matters: addItem() throws RuntimeException for a mandatory bundle, an
        // unavailable product and a passed deadline too, so a bare type assertion would pass
        // on any of them.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~kapacita~');

        $this->cartService()->addItem($kosik, $variant);
    }

    /**
     * Merch má nad rámec stavu produktu ještě termín prodeje celé sekce, takže stránka
     * nechaná otevřená přes termín — nebo přímý požadavek na API — nesmí dokoupit.
     *
     * @test
     */
    public function predmetPoTerminuProdejeNejdeKoupit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: 10);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        // Po termínu, na rozdíl od ostatních testů, které si „teď“ drží na začátku ročníku.
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-12-31 23:59:59'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~skončil~');

        $this->cartService()->addItem($kosik, $variant);
    }

    /**
     * @test
     */
    public function neomezenaZasobaSeNevycerpa(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: null);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        for ($kus = 0; $kus < 3; ++$kus) {
            $this->cartService()->addItem($kosik, $variant);
        }
        $this->entityManager()->flush();

        // Both halves are needed: CapacityManager::purchase() early-returns for unlimited
        // stock, so the NULL alone cannot change and would stay green even if the product
        // had become unbuyable.
        self::assertSame(
            3,
            $this->pocetNakupu($zakaznik, $variant),
            'Předmět s neomezenou zásobou se musí dát koupit opakovaně',
        );
        self::assertNull(
            $this->zbyvajiciKusy($variant),
            'Předmět bez omezeného počtu kusů musí zůstat bez omezení',
        );
    }

    /**
     * @test
     */
    public function objednavkaUlozicenuPlatnouVOkamzikuNakupu(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $variant = $this->vytvorPredmet(kusuVyrobeno: 10, cena: '50.00');
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $drivKoupeno = $this->cartService()->addItem($kosik, $variant);
        $this->entityManager()->flush();

        $variant->setPrice('999.00');
        $variant->getProduct()->setCurrentPrice('999.00');
        $this->entityManager()->flush();

        $pozdejiKoupeno = $this->cartService()->addItem($kosik, $variant);
        $this->entityManager()->flush();

        // Two items, one price change between them: the pair is what makes this falsifiable.
        // Re-reading only the first would pass against any implementation, because nothing
        // propagates a price change onto an already-stored row.
        // Čte se z databáze, ne z entity: getPurchasePrice() by vrátil hodnotu, kterou do
        // identity map před chvílí zapsal sám CartService, takže by uloženého řádku nesáhl.
        self::assertSame(
            '50.00',
            $this->ulozenaCena($drivKoupeno),
            'Nákupní cena se musí zafixovat při nákupu, pozdější změna ceníku ji nesmí přepsat',
        );
        self::assertSame(
            '999.00',
            $this->ulozenaCena($pozdejiKoupeno),
            'Nákup po zdražení se musí uložit za novou cenu — jinak test nedokazuje nic o zamrznutí',
        );
    }
}
