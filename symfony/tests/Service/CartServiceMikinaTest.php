<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
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
 * Mikiny se dřív kupovaly formulářem (`Shop::zpracujPredmety()`, klíč `shopM`) a hlídání
 * zásoby pokrývaly testy nad tou cestou. Formulář je pryč, nakupuje se přes košík — tyhle
 * testy drží tentýž záměr na nové cestě.
 */
class CartServiceMikinaTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    /**
     * Termíny prodeje jsou PHP konstanty, které testovací bootstrap nedefinuje, ale
     * `prodejMikinUkoncen()` je čte natvrdo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $vychozi = SystemoveNastaveni::zGlobals();
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $vychozi->dejVychoziHodnotu($klic));
        }

        // Termíny prodeje leží uprostřed ročníku, takže „teď" musí na jeho začátek —
        // jinak by se všechno odmítlo jako po termínu.
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
            UserEntityStructure::login => 'kupujici_' . uniqid(),
            UserEntityStructure::email => 'kupujici_' . uniqid() . '@example.invalid',
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

    /**
     * Mikina nese `predmet` i `mikina` — tag se musí přidat přes entitu, jinak `hasTag()`
     * čte prázdnou kolekci a kontroly, které na něm visí, se nespustí.
     */
    private function vytvorMikinu(int $kusuVyrobeno): ProductVariant
    {
        $kod = 'mikina-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Mikina');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('600.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setProducedQuantity($kusuVyrobeno);
        $produkt->addTag($this->tag(ProductTagCode::PREDMET));
        $produkt->addTag($this->tag(ProductTagCode::MIKINA));
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('L');
        $varianta->setCode($kod . '-l');
        $varianta->setPrice('600.00');
        $varianta->setRemainingQuantity($kusuVyrobeno);
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    private function zbyvajiciKusy(ProductVariant $varianta): ?int
    {
        $zbyva = $this->connection()->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :id',
            [
                'id' => $varianta->getId(),
            ],
        );

        return $zbyva === null || $zbyva === false
            ? null
            : (int) $zbyva;
    }

    /**
     * @test
     */
    public function mikinuJdeKoupitPresKosik(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorMikinu(kusuVyrobeno: 5);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $polozka = $this->cartService()->addItem($kosik, $varianta);

        self::assertSame('600.00', $polozka->getPurchasePrice());
        self::assertSame(4, $this->zbyvajiciKusy($varianta), 'Koupený kus se musí odečíst ze zásoby');
    }

    /**
     * Termín prodeje mikin hlídal formulář; ten je pryč, takže ho musí hlídat zápis.
     * Bez toho by stránka nechaná otevřená přes půlnoc — nebo ručně sestavený požadavek —
     * koupila i po termínu.
     *
     * @test
     */
    public function poTerminuMikinuNejdeKoupit(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorMikinu(kusuVyrobeno: 5);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: SystemoveNastaveni::zGlobals()->prodejMikinDo()->modifyStrict('+1 day'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~skončil~');

        $this->cartService()->addItem($kosik, $varianta);
    }

    /**
     * Dřív to hlídal formulář: objednávka nad zásobu skončila chybou a nezapsala nic.
     *
     * @test
     */
    public function nakupNadZasobuMikinNeprojdeANezapiseNic(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorMikinu(kusuVyrobeno: 2);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->cartService()->addItem($kosik, $varianta);
        $this->cartService()->addItem($kosik, $varianta);

        $chyba = null;
        try {
            $this->cartService()->addItem($kosik, $varianta);
        } catch (\RuntimeException $zachycena) {
            $chyba = $zachycena;
        }

        self::assertNotNull($chyba, 'Třetí kus při zásobě 2 musí skončit chybou');
        self::assertSame(0, $this->zbyvajiciKusy($varianta), 'Odmítnutý kus nesmí zásobu přetáhnout do mínusu');
        self::assertSame(
            2,
            (int) $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = :produkt',
                [
                    'produkt' => $varianta->getProduct()->getId(),
                ],
            ),
            'Zapsat se smí jen to, co se vešlo do zásoby',
        );
    }
}
