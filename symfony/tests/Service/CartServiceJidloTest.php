<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductBundle;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\CartService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` znamená „objednat **a měnit**": po termínu jsou počty
 * nahlášené v jídelně, takže se ani nepřidává, ani neruší. Zamčená matice sama nestačí —
 * stránka nechaná otevřená přes termín i ručně sestavený požadavek jdou kolem ní.
 */
class CartServiceJidloTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected function setUp(): void
    {
        parent::setUp();

        $vychozi = SystemoveNastaveni::zGlobals();
        foreach ([
            'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $vychozi->dejVychoziHodnotu($klic));
        }

        // Termín leží uprostřed ročníku, takže „teď" musí na jeho začátek — jinak by se
        // všechno odmítlo jako po termínu.
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
            UserEntityStructure::login => 'stravnik_' . uniqid(),
            UserEntityStructure::email => 'stravnik_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $zakaznik;
    }

    private function tag(ProductTagCode $kod): ProductTag
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => $kod->value,
                'name' => $kod->value,
            ],
        );

        $tag = $this->entityManager()
            ->getRepository(ProductTag::class)
            ->findOneBy([
                'code' => $kod->value,
            ]);
        self::assertNotNull($tag, 'Tag se nepodařilo založit');

        return $tag;
    }

    private function vytvorJidlo(): ProductVariant
    {
        $kod = 'obed-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Oběd čtvrtek');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('140.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setAccommodationDay(1);
        $produkt->addTag($this->tag(ProductTagCode::JIDLO));
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('porce');
        $varianta->setCode($kod . '-p');
        $varianta->setPrice('140.00');
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function vytvorTricko(): ProductVariant
    {
        $kod = 'tricko-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Tričko');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('300.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->addTag($this->tag(ProductTagCode::PREDMET));
        $produkt->addTag($this->tag(ProductTagCode::TRICKO));
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('L');
        $varianta->setCode($kod . '-l');
        $varianta->setPrice('300.00');
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function poTerminu(): void
    {
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: SystemoveNastaveni::zGlobals()->prodejJidlaDo()->modifyStrict('+1 day'),
        );
    }

    /**
     * @test
     */
    public function jidloJdeKoupitPredTerminem(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorJidlo();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $polozka = $this->cartService()->addItem($kosik, $varianta);

        self::assertSame('140.00', $polozka->getPurchasePrice());
    }

    /**
     * @test
     */
    public function poTerminuJidloNejdeKoupit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorJidlo();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->poTerminu();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~skončil~');

        $this->cartService()->addItem($kosik, $varianta);
    }

    /**
     * Tady se jídlo liší od merche: po termínu nejde ani vrátit. Jídelna už má počty, takže
     * zrušená porce se stejně uvaří a zaplatí — vrácení by ji jen odúčtovalo tomu, kdo si ji
     * objednal.
     *
     * @test
     */
    public function poTerminuJidloNejdeAniZrusit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorJidlo();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);
        $polozka = $this->cartService()->addItem($kosik, $varianta);

        $this->poTerminu();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~skončil~');

        $this->cartService()->removeItem($kosik, $polozka);
    }

    /**
     * Povinný balíček jde v `RemoveFromCartProcessor` druhou větví, na `removeBundle()`.
     * Bez téže kontroly by se jídlo po termínu zrušilo právě tudy.
     *
     * @test
     */
    public function poTerminuNejdeJidloZrusitAniPresBalicek(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorJidlo();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);
        $polozka = $this->cartService()->addItem($kosik, $varianta);

        $balicek = new ProductBundle();
        $balicek->setName('Povinný balíček');
        $this->entityManager()->persist($balicek);
        $polozka->setBundle($balicek);
        $this->entityManager()->flush();

        $this->poTerminu();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~skončil~');

        $this->cartService()->removeBundle($kosik, $balicek);
    }

    /**
     * Merch se po svém termínu ruší dál — nikdo za něj zatím dodavateli nezaplatil.
     * Legacy `Shop::zrusZrusitelneLetosniObjednavky()` ho ze stejného důvodu nezachovává,
     * takže zámek jídla se na něj nesmí rozlézt.
     *
     * @test
     */
    public function merchJdeZrusitIPoTerminuJidla(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $tricko = $this->vytvorTricko();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);
        $polozka = $this->cartService()->addItem($kosik, $tricko);

        $this->poTerminu();

        $this->cartService()->removeItem($kosik, $polozka);

        self::assertCount(0, $kosik->getItems(), 'Termín jídla nesmí zamykat rušení merche');
    }

    /**
     * Před termínem se ruší normálně — kontrola nesmí zavřít i to, co je ještě otevřené.
     *
     * @test
     */
    public function predTerminemJdeJidloZrusit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorJidlo();
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);
        $polozka = $this->cartService()->addItem($kosik, $varianta);

        $this->cartService()->removeItem($kosik, $polozka);

        self::assertCount(0, $kosik->getItems(), 'Před termínem se jídlo z košíku odebrat má');
    }
}
