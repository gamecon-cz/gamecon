<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\ProductBundle;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\CartService;
use App\Service\OperatorOverride;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Orgovská a vypravěčská trička jsou vázaná na právo, ne na cenu. Kontrola dlouho žila jen
 * na čtecí straně (produkt se nenabídl), takže ručně sestavený požadavek na košík ho koupil
 * komukoli — tenhle test drží, že zápis to odmítne.
 */
class CartServiceRestrictedProductTest extends AbstractDatabaseKernelTestCase
{
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
     * Tag se musí přidat přes entitu, ne joinem v SQL: `hasTag()` čte namapovanou kolekci,
     * takže po syrovém insertu zůstane prázdná a kontrola se vůbec nespustí.
     */
    private function vytvorTricko(ProductTagCode ...$tagy): ProductVariant
    {
        $kod = 'tricko-' . uniqid();

        $produkt = new Product();
        $produkt->setName('Tričko');
        $produkt->setCode($kod);
        $produkt->setCurrentPrice('200.00');
        $produkt->setDescription('');
        $produkt->setState(ProductStateEnum::PUBLIC);
        $produkt->setProducedQuantity(10);
        foreach ($tagy as $tag) {
            $produkt->addTag($this->tag($tag));
        }
        $this->entityManager()->persist($produkt);
        $this->entityManager()->flush();

        $varianta = new ProductVariant();
        $varianta->setProduct($produkt);
        $varianta->setName('L');
        $varianta->setCode($kod . '-l');
        $varianta->setPrice('200.00');
        $varianta->setRemainingQuantity(10);
        $varianta->setPosition(0);
        $produkt->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $varianta;
    }

    /**
     * @test
     */
    public function beznemuZakaznikoviNejdeProdatOrgovskeTricko(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~nemáš nárok~');

        $this->cartService()->addItem($kosik, $varianta);
    }

    /**
     * @test
     */
    public function beznemuZakaznikoviNejdeProdatVypravecskeTricko(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO, ProductTagCode::TRICKO_MODRE);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~nemáš nárok~');

        $this->cartService()->addItem($kosik, $varianta);
    }

    /**
     * Kontrola se nesmí rozšířit na všechna trička — běžné tričko žádné právo nevyžaduje.
     *
     * @test
     */
    public function beznemuZakaznikoviJdeProdatBezneTricko(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $polozka = $this->cartService()->addItem($kosik, $varianta);

        self::assertSame('200.00', $polozka->getPurchasePrice());
    }

    /**
     * `addBundle()` staví položky mimo `addItem()`, takže kontrolu musí volat samo.
     * Jinak by stačilo omezené tričko zabalit do balíčku a prodá se komukoli.
     *
     * @test
     */
    public function omezeneTrickoNejdeProdatAniVBalicku(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE);

        $balicek = new ProductBundle();
        $balicek->setName('Orgovský balíček');
        $balicek->setForced(false);
        $balicek->setApplicableToRoles([]);
        $balicek->addVariant($varianta);
        $this->entityManager()->persist($balicek);
        $this->entityManager()->flush();

        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~nemáš nárok~');

        $this->cartService()->addBundle($kosik, $balicek);
    }

    /**
     * Objednávka bez zákazníka se nesmí stát důvodem, proč omezené tričko projde.
     * `Order::setCustomer()` bere null a KFC si objednávku staví ručně; databáze sice
     * `customer_id NOT NULL` vynutí, ale až při zápisu — kontrola se na to nesmí spolehnout.
     *
     * @test
     */
    public function objednavkaBezZakaznikaOmezeneTrickoNekoupi(): void
    {
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE);

        $kosik = new Order();
        $kosik->setYear((int) ROCNIK);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~nemáš nárok~');

        $this->cartService()->addItem($kosik, $varianta);
    }

    /**
     * Pult smí prodat orgovské tričko komukoli — komu ho vydá, rozhoduje obsluha, stejně
     * jako u zásoby rezervované pro organizátory. Obejití se musí zapsat: právě tohle
     * je ze všech obejití to nejvíc auditované.
     *
     * @test
     */
    public function pultSmiProdatOrgovskeTrickoKomukoliAZapiseTo(): void
    {
        $zakaznik = $this->vytvorZakaznika();
        $obsluha = $this->vytvorZakaznika();
        $varianta = $this->vytvorTricko(ProductTagCode::TRICKO, ProductTagCode::TRICKO_CERVENE);
        $kosik = $this->cartService()->getOrCreateCart($zakaznik);

        $polozka = $this->cartService()->addItem(
            $kosik,
            $varianta,
            override: OperatorOverride::deskSale($obsluha),
        );

        self::assertSame('200.00', $polozka->getPurchasePrice());

        $log = $this->connection()->fetchOne(
            'SELECT override_log FROM shop_nakupy WHERE id_nakupu = :id',
            [
                'id' => $polozka->getId(),
            ],
        );
        self::assertIsString($log, 'Obejití se musí zapsat do override_log');
        self::assertStringContainsString(
            OperatorOverride::GUARD_RESTRICTED_PRODUCT,
            $log,
            'V logu musí být vidět, že pult obešel omezení na tričko',
        );
    }
}
