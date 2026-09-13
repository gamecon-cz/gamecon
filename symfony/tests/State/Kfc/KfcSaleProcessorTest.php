<?php

declare(strict_types=1);

namespace App\Tests\State\Kfc;

use ApiPlatform\Metadata\Post;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleItemInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\State\Kfc\KfcSaleProcessor;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Prodej na pultu proti skutečné databázi.
 *
 * Předchozí verze mockovala Connection, takže ověřovala tvar SQL, ne jeho účinek — a právě
 * proto jí uniklo, že se anonymní prodej nikdy nepřipsal do plateb.
 */
class KfcSaleProcessorTest extends AbstractDatabaseKernelTestCase
{
    private const LOGIN_ANONYMNIHO_KUPUJICIHO = 'ANONYM';

    private ?SystemoveNastaveni $puvodniNastaveni = null;

    /**
     * `SystemoveNastaveni::prodejPredmetuBezTricekDo()` čte tyhle konstanty natvrdo a testovací
     * bootstrap je nedefinuje. Zároveň leží uprostřed ročníku, takže „teď" musí na začátek
     * roku — jinak by pult prodával jen přes obejití a testy by neměřily, co mají.
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

    private function anonymniKupujici(): User
    {
        $kupujici = $this->entityManager()
            ->getRepository(User::class)
            ->findOneBy([
                'login' => self::LOGIN_ANONYMNIHO_KUPUJICIHO,
            ]);
        self::assertNotNull($kupujici, 'Anonymní kupující musí existovat z migrace');

        return $kupujici;
    }

    private function processor(): KfcSaleProcessor
    {
        return static::getContainer()->get(KfcSaleProcessor::class);
    }

    private function prihlasOperatora(): User
    {
        /** @var User $operator */
        $operator = UserFactory::createOne([
            UserEntityStructure::login => 'kfc_operator_' . uniqid(),
            UserEntityStructure::email => 'kfc_operator_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        static::getContainer()->get('security.token_storage')->setToken(
            new PostAuthenticationToken($operator, 'main', ['ROLE_ADMIN']),
        );

        return $operator;
    }

    private function vytvorPredmet(?int $kusuVyrobeno, string $cena = '50.00'): Product
    {
        // product_tag.created_at je NOT NULL bez defaultu a na entitě není namapované, takže
        // tag jde založit jen v SQL — a musí se načíst zpět jako spravovaná entita, jinak
        // by ho produkt přes addTag() nespároval.
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
        self::assertNotNull($tag);

        $kod = 'kfc-' . uniqid();

        $predmet = new Product();
        $predmet->setName('Pultové tričko');
        $predmet->setCode($kod);
        $predmet->setCurrentPrice($cena);
        $predmet->setDescription('');
        $predmet->setState(ProductStateEnum::PUBLIC);
        $predmet->setProducedQuantity($kusuVyrobeno);
        $predmet->addTag($tag);
        $this->entityManager()->persist($predmet);

        $varianta = new ProductVariant();
        $varianta->setProduct($predmet);
        $varianta->setName('jedna velikost');
        $varianta->setCode($kod . '-1');
        $varianta->setPrice($cena);
        $varianta->setRemainingQuantity($kusuVyrobeno);
        $varianta->setPosition(0);
        $predmet->addVariant($varianta);
        $this->entityManager()->persist($varianta);
        $this->entityManager()->flush();

        return $predmet;
    }

    private function prodej(Product ...$predmety): KfcSaleInputDto
    {
        $vstup = new KfcSaleInputDto();
        foreach ($predmety as $predmet) {
            $polozka = new KfcSaleItemInputDto();
            $polozka->productId = (int) $predmet->getId();
            $polozka->quantity = 1;
            $vstup->items[] = $polozka;
        }

        return $vstup;
    }

    private function zpracuj(KfcSaleInputDto $vstup): KfcSaleOutputDto
    {
        return $this->processor()->process($vstup, new Post());
    }

    /**
     * @test
     */
    public function prodejZapiseNakupNaSystemovehoUzivatele(): void
    {
        $operator = $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $vysledek = $this->zpracuj($this->prodej($predmet));

        self::assertSame(1, $vysledek->soldItems);
        self::assertSame('50', $vysledek->totalPrice, 'Pokladna počítá v celých korunách');

        $nakup = $this->connection()->fetchAssociative(
            'SELECT id_uzivatele, id_objednatele, product_name FROM shop_nakupy WHERE id_predmetu = :id',
            [
                'id' => $predmet->getId(),
            ],
        );
        self::assertSame(
            (int) $this->anonymniKupujici()->getId(),
            (int) $nakup['id_uzivatele'],
            'Kupujícím je anonymní účet, ne SYSTEM',
        );
        self::assertSame(
            (int) $operator->getId(),
            (int) $nakup['id_objednatele'],
            'Musí být vidět, kdo prodej na pultu provedl',
        );
        self::assertSame('Pultové tričko', $nakup['product_name'], 'Nákup si musí nést snapshot produktu');
    }

    /**
     * @test
     */
    public function anonymniProdejSePripiseDoPlateb(): void
    {
        $operator = $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10, cena: '50.00');

        $this->zpracuj($this->prodej($predmet));

        $platba = $this->connection()->fetchAssociative(
            // Filtruje se na tohohle operátora (login je uniqid), ne jen na SYSTEM — jinak
            // by test mohl číst platbu z jiného případu.
            'SELECT castka, provedl, poznamka FROM platby
             WHERE id_uzivatele = :system AND provedl = :operator',
            [
                'system'   => $this->anonymniKupujici()->getId(),
                'operator' => $operator->getId(),
            ],
        );
        self::assertNotFalse($platba, 'Anonymní prodej se musí připsat, jinak SYSTEMu roste fiktivní dluh');
        self::assertSame('50.00', $platba['castka']);
        self::assertSame((int) $operator->getId(), (int) $platba['provedl']);
    }

    /**
     * Po termínu prodeje merche je obejitím každý pultový prodej, takže v logu musí být vidět,
     * že jde o KFC — jinak by se v něm nedalo nic rozlišit.
     *
     * @test
     */
    public function prodejPoTerminuJeVLoguOznacenyJakoKfc(): void
    {
        $operator = $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-12-31 23:59:59'),
        );

        $this->zpracuj($this->prodej($predmet));

        $log = $this->connection()->fetchOne(
            'SELECT override_log FROM shop_nakupy WHERE id_predmetu = :id',
            [
                'id' => $predmet->getId(),
            ],
        );
        self::assertNotNull($log, 'Prodej po termínu je obejití a musí se zaznamenat');

        $zaznamy = json_decode((string) $log, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $zaznamy);
        self::assertSame('deadline', $zaznamy[0]['guard']);
        self::assertSame('kfc', $zaznamy[0]['source'], 'Z logu musí být poznat, že prodej přišel z pultu');
        self::assertSame((int) $operator->getId(), $zaznamy[0]['by']);
    }

    /**
     * @test
     */
    public function prodejVTerminuNemaZadneObejiti(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $this->zpracuj($this->prodej($predmet));

        self::assertNull(
            $this->connection()->fetchOne(
                'SELECT override_log FROM shop_nakupy WHERE id_predmetu = :id',
                [
                    'id' => $predmet->getId(),
                ],
            ),
            'Dokud prodej běží, není co obcházet — log musí zůstat prázdný',
        );
    }

    /**
     * Pokladna účtuje celé koruny, takže připsaná platba musí sedět na tutéž částku. Jinak
     * by se s první procentní slevou rozešla kasa s účetnictvím o haléře.
     *
     * @test
     */
    public function pokladnaAUcetnictviSediNaStejnouCastku(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10, cena: '42.50');

        $vysledek = $this->zpracuj($this->prodej($predmet));

        $pripsano = $this->connection()->fetchOne(
            'SELECT castka FROM platby WHERE id_uzivatele = :system AND poznamka = :poznamka
             ORDER BY provedeno DESC LIMIT 1',
            [
                'system'   => $this->anonymniKupujici()->getId(),
                'poznamka' => 'anonymní prodej',
            ],
        );

        self::assertSame('43', $vysledek->totalPrice, 'Pokladna zaokrouhluje na celé koruny');
        self::assertSame(
            43.0,
            (float) $pripsano,
            'Připsat se musí přesně to, co zákazník zaplatil, ne nezaokrouhlená cena',
        );
    }

    /**
     * @test
     */
    public function predmetSVicVariantamiNejdeProdatNaslepo(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $druhaVarianta = new ProductVariant();
        $druhaVarianta->setProduct($predmet);
        $druhaVarianta->setName('druhá velikost');
        $druhaVarianta->setCode($predmet->getCode() . '-2');
        $druhaVarianta->setPrice('50.00');
        $druhaVarianta->setRemainingQuantity(10);
        $druhaVarianta->setPosition(1);
        $predmet->addVariant($druhaVarianta);
        $this->entityManager()->persist($druhaVarianta);
        $this->entityManager()->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~víc variant~');

        $this->zpracuj($this->prodej($predmet));
    }

    /**
     * Chyba v prodeji nesmí zavřít EntityManager — jinak z ní místo srozumitelné hlášky
     * spadne celý request.
     *
     * @test
     */
    public function chybaProdejeNezavreEntityManager(): void
    {
        $this->prihlasOperatora();

        $vstup = new KfcSaleInputDto();
        $polozka = new KfcSaleItemInputDto();
        $polozka->productId = 999999;
        $polozka->quantity = 1;
        $vstup->items[] = $polozka;

        try {
            $this->zpracuj($vstup);
            self::fail('Neznámý produkt musí skončit chybou');
        } catch (\RuntimeException) {
            // očekávané
        }

        self::assertTrue(
            $this->entityManager()->isOpen(),
            'Po neúspěšném prodeji musí jít EntityManager dál používat',
        );
    }

    /**
     * Kusy odložené organizátorům smí pult prodat komukoli — komu je vydá, rozhoduje obsluha.
     * Legacy prodej rezervace vůbec neznal, takže bez tohohle by pult uměl míň než dřív.
     *
     * @test
     */
    public function pultProdaIKusyRezervovanaProOrganizatory(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 3);

        // Všechny tři kusy jsou odložené organizátorům — účastníkovi by nezbylo nic.
        $this->connection()->executeStatement(
            'UPDATE product_variant SET reserved_for_organizers = 3 WHERE product_id = :id',
            [
                'id' => $predmet->getId(),
            ],
        );
        // Jen osvěžit variantu, ne clear(): ten by odpojil i přihlášeného operátora a Doctrine
        // by ho při zápisu považovala za novou entitu.
        $this->entityManager()->refresh($predmet->getVariants()->first());

        $vysledek = $this->zpracuj($this->prodej($predmet));

        self::assertSame(1, $vysledek->soldItems, 'Pult musí prodat i z rezervovaných kusů');
        self::assertSame(
            2,
            (int) $this->connection()->fetchOne(
                'SELECT remaining_quantity FROM product_variant WHERE product_id = :id',
                [
                    'id' => $predmet->getId(),
                ],
            ),
        );
    }

    /**
     * @test
     */
    public function pultNeprodaVicNezJeCelkemNaSklade(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 0);

        $this->connection()->executeStatement(
            'UPDATE product_variant SET reserved_for_organizers = 5 WHERE product_id = :id',
            [
                'id' => $predmet->getId(),
            ],
        );
        $this->entityManager()->refresh($predmet->getVariants()->first());

        // Rezervaci pult obejít smí, celkovou zásobu ne — prodat neexistující kus nelze.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~kapacita~');

        $this->zpracuj($this->prodej($predmet));
    }

    /**
     * Platba musí vědět, ke které objednávce patří. Bez té vazby po nedokončeném prodeji
     * zůstala viset osiřelá platba a nikdo se to nedozvěděl — v datech jedna taková je.
     *
     * @test
     */
    public function platbaZnaSvouObjednavku(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $this->zpracuj($this->prodej($predmet));

        $vazba = $this->connection()->fetchAssociative(
            'SELECT platby.order_id, shop_order.customer_id
             FROM platby JOIN shop_order ON shop_order.id = platby.order_id
             WHERE platby.id_uzivatele = :kupujici AND platby.poznamka = :poznamka',
            [
                'kupujici' => $this->anonymniKupujici()->getId(),
                'poznamka' => 'anonymní prodej',
            ],
        );

        self::assertNotFalse($vazba, 'Platba za prodej na pultu musí odkazovat na objednávku');
        self::assertSame(
            (int) $this->anonymniKupujici()->getId(),
            (int) $vazba['customer_id'],
            'A ta objednávka musí patřit témuž kupujícímu',
        );
    }

    /**
     * @test
     */
    public function anonymniKupujiciNemaZadneRole(): void
    {
        // Bez rolí neprojde slevový engine — anonymní zákazník na pultu nesmí dostat slevu,
        // kterou by účet zdědil jen tím, že mu někdo roli přidělí.
        self::assertSame(
            0,
            (int) $this->connection()->fetchOne(
                'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = :id',
                [
                    'id' => $this->anonymniKupujici()->getId(),
                ],
            ),
        );
    }

    /**
     * @test
     */
    public function prodejOdecteKusZeZasoby(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 10);

        $this->zpracuj($this->prodej($predmet));

        self::assertSame(
            9,
            (int) $this->connection()->fetchOne(
                'SELECT remaining_quantity FROM product_variant WHERE product_id = :id',
                [
                    'id' => $predmet->getId(),
                ],
            ),
            'Zásoba se musí snížit přes CapacityManager, ne dopočítávat z počtu nákupů',
        );
    }

    /**
     * @test
     */
    public function vyprodanyPredmetNejdeProdat(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~kapacita~');

        $this->zpracuj($this->prodej($predmet));
    }

    /**
     * @test
     */
    public function neznamyPredmetSkonciChybou(): void
    {
        $this->prihlasOperatora();

        $vstup = new KfcSaleInputDto();
        $polozka = new KfcSaleItemInputDto();
        $polozka->productId = 999999;
        $polozka->quantity = 1;
        $vstup->items[] = $polozka;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~nebyl nalezen~');

        $this->zpracuj($vstup);
    }

    /**
     * @test
     */
    public function neomezenaZasobaSeNevycerpa(): void
    {
        $this->prihlasOperatora();
        $predmet = $this->vytvorPredmet(kusuVyrobeno: null);

        $vysledek = $this->zpracuj($this->prodej($predmet));

        self::assertSame(1, $vysledek->soldItems);
        self::assertNull(
            $this->connection()->fetchOne(
                'SELECT remaining_quantity FROM product_variant WHERE product_id = :id',
                [
                    'id' => $predmet->getId(),
                ],
            ),
        );
    }

    /**
     * @test
     */
    public function prodejVicPolozekNaraz(): void
    {
        $this->prihlasOperatora();
        $prvni = $this->vytvorPredmet(kusuVyrobeno: 10, cena: '50.00');
        $druhy = $this->vytvorPredmet(kusuVyrobeno: 10, cena: '30.00');

        $vysledek = $this->zpracuj($this->prodej($prvni, $druhy));

        self::assertSame(2, $vysledek->soldItems);
        self::assertSame('80', $vysledek->totalPrice);
    }
}
