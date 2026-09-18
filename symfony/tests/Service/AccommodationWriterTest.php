<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\AccommodationWriter;
use App\Service\BreakfastCanceller;
use App\Service\CartService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Exercises the real SQL: the capacity guard lives in the INSERT's WHERE clause, so a
 * mocked connection would prove nothing about the behaviour that matters here.
 */
class AccommodationWriterTest extends AbstractDatabaseKernelTestCase
{
    private const ROK = 2026;

    /**
     * @var array<int, ProductVariant> keyed by day
     */
    private array $noci = [];

    private ?SystemoveNastaveni $puvodniNastaveni = null;

    /**
     * Snídaně se kupují košíkem, který drží termín prodeje jídla. Ten leží uprostřed
     * ročníku, takže bez pevného „teď" by testy začaly padat dnem, kdy uplyne.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $vychozi = SystemoveNastaveni::zGlobals();
        try_define('JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE', $vychozi->dejVychoziHodnotu('JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE'));

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

    private function writer(): AccommodationWriter
    {
        return static::getContainer()->get(AccommodationWriter::class);
    }

    /**
     * One accommodation product with a variant per night, each holding its own capacity —
     * the shape the day-variant migration produces.
     */
    private function pripravUbytovani(int $kusuVyrobeno = 5): void
    {
        $connection = $this->connection();
        $kod = 'test-' . uniqid();

        // product_tag.created_at is NOT NULL without a default and unmapped on the entity,
        // so a tag can only be created in SQL.
        $connection->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::UBYTOVANI->value,
                'name' => 'Ubytování',
            ],
        );
        $product = new Product();
        $product->setName('Testovací pokoj');
        $product->setCode($kod);
        $product->setCurrentPrice('100.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setProducedQuantity($kusuVyrobeno);
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        // Linked in SQL: the tag row is created outside the ORM (created_at is unmapped), so
        // associating it through the entity leaves the join row unwritten.
        $connection->executeStatement(
            'INSERT INTO product_product_tag (product_id, tag_id)
             SELECT :product, id FROM product_tag WHERE code = :code',
            [
                'product' => $product->getId(),
                'code'    => ProductTagCode::UBYTOVANI->value,
            ],
        );

        foreach ([
            0 => 'středa',
            1 => 'čtvrtek',
            2 => 'pátek',
        ] as $den => $nazev) {
            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setName($nazev);
            $variant->setCode($kod . '-' . $den);
            $variant->setAccommodationDay($den);
            $variant->setRemainingQuantity($kusuVyrobeno);
            $variant->setPosition($den);
            $product->addVariant($variant);
            $this->entityManager()->persist($variant);

            // The writer reads the produced count off the night's own legacy row, matched by
            // variant code — the row the migration leaves behind for each absorbed night.
            $this->entityManager()->flush();
            $connection->executeStatement(
                'INSERT INTO shop_predmety (nazev, kod_predmetu, kusu_vyrobeno, cena_aktualni, stav, ubytovani_den)
                 VALUES (:nazev, :kod, :kusu, 100, :stav, :den)',
                [
                    'nazev' => 'Testovací pokoj ' . $nazev,
                    'kod'   => $variant->getCode(),
                    'kusu'  => $kusuVyrobeno,
                    'stav'  => ProductStateEnum::PUBLIC->value,
                    'den'   => $den,
                ],
            );

            $this->noci[$den] = $variant;
        }
    }

    /**
     * A night whose price includes breakfast, and a separately bought breakfast for the
     * morning after it.
     *
     * @return array{0: int, 1: int, 2: string} night variant id, breakfast variant id, its name
     */
    private function pripravHotelSeSnidani(int $den): array
    {
        $connection = $this->connection();
        $kod = 'hotel-' . uniqid();

        $hotel = new Product();
        $hotel->setName('Hotel se snídaní');
        $hotel->setCode($kod);
        $hotel->setCurrentPrice('500.00');
        $hotel->setDescription('');
        $hotel->setState(ProductStateEnum::PUBLIC);
        $hotel->setProducedQuantity(5);
        $hotel->setBreakfastIncluded(true);
        $this->entityManager()->persist($hotel);
        $this->entityManager()->flush();

        $noc = new ProductVariant();
        $noc->setProduct($hotel);
        $noc->setName('noc');
        $noc->setCode($kod . '-' . $den);
        $noc->setAccommodationDay($den);
        $noc->setRemainingQuantity(5);
        $noc->setPosition($den);
        $hotel->addVariant($noc);
        $this->entityManager()->persist($noc);
        $this->entityManager()->flush();

        $connection->executeStatement(
            'INSERT INTO product_product_tag (product_id, tag_id)
             SELECT :product, id FROM product_tag WHERE code = :code',
            [
                'product' => $hotel->getId(),
                'code'    => ProductTagCode::UBYTOVANI->value,
            ],
        );
        $connection->executeStatement(
            'INSERT INTO shop_predmety (nazev, kod_predmetu, kusu_vyrobeno, cena_aktualni, stav, ubytovani_den)
             VALUES (:nazev, :kod, 5, 500, :stav, :den)',
            [
                'nazev' => 'Hotel se snídaní',
                'kod'   => $noc->getCode(),
                'stav'  => ProductStateEnum::PUBLIC->value,
                'den'   => $den,
            ],
        );

        // The breakfast this night covers: night N covers the morning of day N+1.
        $snidaneProdukt = new Product();
        $snidaneNazev = 'Snídaně testovací ' . ($den + 1);
        $snidaneProdukt->setName($snidaneNazev);
        $snidaneProdukt->setCode('snidane-' . uniqid());
        $snidaneProdukt->setCurrentPrice('50.00');
        $snidaneProdukt->setDescription('');
        $snidaneProdukt->setState(ProductStateEnum::PUBLIC);
        $this->entityManager()->persist($snidaneProdukt);
        $this->entityManager()->flush();

        // Tagged as food, which is what tells a breakfast apart from anything else whose
        // name happens to start with "Snídaně".
        $connection->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::JIDLO->value,
                'name' => 'Jídlo',
            ],
        );
        $connection->executeStatement(
            'INSERT INTO product_product_tag (product_id, tag_id)
             SELECT :product, id FROM product_tag WHERE code = :code',
            [
                'product' => $snidaneProdukt->getId(),
                'code'    => ProductTagCode::JIDLO->value,
            ],
        );

        $snidane = new ProductVariant();
        $snidane->setProduct($snidaneProdukt);
        $snidane->setName('snídaně');
        $snidane->setCode($snidaneProdukt->getCode());
        $snidane->setAccommodationDay($den + 1);
        $snidane->setPosition(0);
        $snidaneProdukt->addVariant($snidane);
        $this->entityManager()->persist($snidane);
        $this->entityManager()->flush();

        return [(int) $noc->getId(), (int) $snidane->getId(), $snidaneNazev];
    }

    private function koupSnidani(User $customer, int $snidaneVariantId): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             SELECT :customer, product_id, id, :year, 50, NOW() FROM product_variant WHERE id = :variant',
            [
                'customer' => $customer->getId(),
                'variant'  => $snidaneVariantId,
                'year'     => self::ROK,
            ],
        );
    }

    private function ucastnik(): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => 'ubytovani_test_' . uniqid(),
            UserEntityStructure::email => 'ubytovani_test_' . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'Ubytovaný Testovací',
        ])->_save()->_real();

        return $user;
    }

    private function pocetNoci(User $customer, int $den): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy
             WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $customer->getId(),
                'variant'  => $this->noci[$den]->getId(),
                'year'     => self::ROK,
            ],
        );
    }

    /**
     * @return int[]
     */
    private function idNoci(int ...$dny): array
    {
        return array_map(fn (int $den): int => (int) $this->noci[$den]->getId(), $dny);
    }

    public function testSavesTwoConsecutiveNights(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->writer()->save($customer, $this->idNoci(0, 1), self::ROK, false);

        self::assertSame(1, $this->pocetNoci($customer, 0));
        self::assertSame(1, $this->pocetNoci($customer, 1));
    }

    /**
     * The day-variant migration reparented every night's variant onto one owner product whose
     * own day is Sunday, but legacy reads ubytovani_den off shop_nakupy.id_predmetu — so
     * storing the parent would price and count every night as Sunday.
     */
    public function testPurchaseRecordsTheNightsOwnLegacyRow(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->writer()->save($customer, $this->idNoci(0, 1), self::ROK, false);

        $dny = $this->connection()->fetchFirstColumn(
            'SELECT shop_predmety.ubytovani_den
             FROM shop_nakupy
             JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
             WHERE shop_nakupy.id_uzivatele = :customer AND shop_nakupy.rok = :year
             ORDER BY shop_predmety.ubytovani_den',
            [
                'customer' => $customer->getId(),
                'year'     => self::ROK,
            ],
        );

        self::assertSame([0, 1], array_map('intval', $dny));
    }

    public function testEmptySetCancelsTheBooking(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();
        $this->writer()->save($customer, $this->idNoci(0, 1), self::ROK, false);

        $this->writer()->save($customer, [], self::ROK, false);

        self::assertSame(0, $this->pocetNoci($customer, 0));
        self::assertSame(0, $this->pocetNoci($customer, 1));
    }

    public function testSavingIsASetNotAnAddition(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();
        $this->writer()->save($customer, $this->idNoci(0, 1), self::ROK, false);

        $this->writer()->save($customer, $this->idNoci(1, 2), self::ROK, false);

        self::assertSame(0, $this->pocetNoci($customer, 0), 'Wednesday should have been dropped');
        self::assertSame(1, $this->pocetNoci($customer, 1));
        self::assertSame(1, $this->pocetNoci($customer, 2));
    }

    public function testNonConsecutiveNightsAreRefused(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->expectExceptionMessage(AccommodationWriter::ERROR_CONSECUTIVE_NIGHTS);

        $this->writer()->save($customer, $this->idNoci(0, 2), self::ROK, false);
    }

    public function testSingleNightIsRefusedWithoutThePermission(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->expectExceptionMessage(AccommodationWriter::ERROR_AT_LEAST_TWO_NIGHTS);

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, false);
    }

    /**
     * The legacy admin screens can book a set these rules reject, and re-sending it unchanged
     * (to edit only the roommate) must not lock the customer out of saving.
     */
    public function testUnchangedNightsAreNotRevalidated(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();
        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);

        // Wednesday alone would fail the two-night rule if it were judged again.
        $this->writer()->save($customer, $this->idNoci(0), self::ROK, false, 'Karel');

        self::assertSame(1, $this->pocetNoci($customer, 0));
    }

    public function testSingleNightIsAllowedWithThePermission(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);

        self::assertSame(1, $this->pocetNoci($customer, 0));
    }

    public function testUnknownVariantIsRefused(): void
    {
        $this->pripravUbytovani();

        $this->expectExceptionMessage('není nabízeným ubytováním');

        $this->writer()->save($this->ucastnik(), [999999999], self::ROK, true);
    }

    public function testRoommateAndDeclineGoOntoTheOrderAndTheAccount(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->writer()->save($customer, [], self::ROK, false, ' Karel Novák ', true);

        $order = $this->connection()->fetchAssociative(
            'SELECT roommate, accommodation_declined FROM shop_order WHERE customer_id = :customer AND year = :year',
            [
                'customer' => $customer->getId(),
                'year'     => self::ROK,
            ],
        );
        self::assertSame('Karel Novák', $order['roommate'], 'stored trimmed');
        self::assertSame(1, (int) $order['accommodation_declined']);

        // Dual-written while the legacy form still reads the account columns.
        $ucet = $this->connection()->fetchAssociative(
            'SELECT ubytovan_s, nechce_ubytovani FROM uzivatele_hodnoty WHERE id_uzivatele = :customer',
            [
                'customer' => $customer->getId(),
            ],
        );
        self::assertSame('Karel Novák', $ucet['ubytovan_s']);
        self::assertSame(1, (int) $ucet['nechce_ubytovani']);
    }

    public function testBookingNightsClearsTheDecline(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();
        $this->writer()->save($customer, [], self::ROK, false, null, true);

        // "I want none" cannot stand next to booked nights, so booking answers the question.
        $this->writer()->save($customer, $this->idNoci(0, 1), self::ROK, false, null, true);

        $declined = $this->connection()->fetchOne(
            'SELECT accommodation_declined FROM shop_order WHERE customer_id = :customer AND year = :year',
            [
                'customer' => $customer->getId(),
                'year'     => self::ROK,
            ],
        );
        self::assertSame(0, (int) $declined);
    }

    /**
     * The guard the whole design turns on: remaining_quantity is stale for accommodation,
     * so a full night has to be recognised from the sold count instead.
     */
    public function testFullNightIsRefusedEvenWhenRemainingQuantitySaysOtherwise(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 2);
        $customer = $this->ucastnik();
        $this->zaplnNoc(0, 2);

        // Leave the column lying that beds are free, exactly as the legacy admin screens do.
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 999 WHERE id = :variant',
            [
                'variant' => $this->noci[0]->getId(),
            ],
        );

        $this->expectExceptionMessage('přeplnit ho smí jen šéf infopultu');

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);
    }

    /**
     * Zásoba na variantě je sice pro rozhodování o kapacitě zastaralá, ale pořád ji čte
     * účastnický košík. Když ji admin prodejem nesníží, e-shop pak nabízí postele, které
     * na pultu někdo právě prodal.
     */
    public function testAdminSaleDecrementsTheVariantStock(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 5);
        $customer = $this->ucastnik();

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);

        self::assertSame(4, $this->zbyvaNaVarianteId($this->noci[0]->getId()), 'Prodej přes admin musí snížit zásobu');
    }

    /**
     * Admin smí prodat i nad kapacitu, takže zásoba musí umět jít do mínusu — jinak by se
     * zastavila na nule a přestala odpovídat tomu, kolik postelí je reálně rozprodáno.
     */
    public function testAdminOverbookingDrivesTheStockNegative(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 1);
        $customer = $this->ucastnik();
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 0 WHERE id = :variant',
            [
                'variant' => $this->noci[0]->getId(),
            ],
        );

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true, mayOverbook: true);

        self::assertSame(-1, $this->zbyvaNaVarianteId($this->noci[0]->getId()), 'Přeplnění musí jít do mínusu');
    }

    /**
     * Zrušení noci přes admin musí zásobu vrátit, jinak by se jednosměrně propadala.
     */
    public function testAdminCancellationReturnsTheStock(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 5);
        $customer = $this->ucastnik();
        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);

        $this->writer()->save($customer, [], self::ROK, true);

        self::assertSame(5, $this->zbyvaNaVarianteId($this->noci[0]->getId()), 'Zrušení musí zásobu vrátit');
    }

    /**
     * shop_nakupy.id_uzivatele is a foreign key, so the beds have to be taken by real users.
     */
    private function zaplnNoc(int $den, int $kusu): void
    {
        for ($i = 0; $i < $kusu; ++$i) {
            $this->connection()->executeStatement(
                'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
                 VALUES (:customer, :product, :variant, :year, 0, NOW())',
                [
                    'customer' => $this->ucastnik()->getId(),
                    'product'  => $this->noci[$den]->getProduct()->getId(),
                    'variant'  => $this->noci[$den]->getId(),
                    'year'     => self::ROK,
                ],
            );
        }
    }

    /**
     * Noc kryje ráno NÁSLEDUJÍCÍHO dne, ne svého vlastního — čtvrteční hotel ruší páteční
     * snídani a té čtvrteční se nedotkne. Dřív to hlídala admin tabulka přes atribut
     * `data-snidane-dny`; ta je pryč, pravidlo zůstává.
     */
    public function testHotelNightCancelsTheNextMorningNotItsOwn(): void
    {
        $customer = $this->ucastnik();
        [$ctvrtecniNoc, $patecniSnidane] = $this->pripravHotelSeSnidani(1);
        [, $ctvrtecniSnidane] = $this->pripravHotelSeSnidani(0);
        $this->koupSnidani($customer, $patecniSnidane);
        $this->koupSnidani($customer, $ctvrtecniSnidane);

        $this->writer()->save($customer, [$ctvrtecniNoc], self::ROK, true);

        self::assertSame(0, $this->pocetNakupu($customer, $patecniSnidane), 'Čtvrteční noc ruší páteční snídani');
        self::assertSame(1, $this->pocetNakupu($customer, $ctvrtecniSnidane), 'Čtvrteční snídaně zůstává, tu noc nekryje');
    }

    public function testBookingAHotelNightCancelsTheBreakfastItCovers(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane] = $this->pripravHotelSeSnidani(1);
        $this->koupSnidani($customer, $snidane);

        $this->writer()->save($customer, [$noc], self::ROK, true);

        self::assertSame(0, $this->pocetNakupu($customer, $snidane), 'breakfast should be cancelled');
    }

    public function testCancelledBreakfastIsOfferedBackOnceTheNightIsDropped(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane, $nazev] = $this->pripravHotelSeSnidani(1);
        $this->koupSnidani($customer, $snidane);
        $this->writer()->save($customer, [$noc], self::ROK, true);

        $this->writer()->save($customer, [], self::ROK, true);

        self::assertSame(
            [$nazev],
            array_values($this->breakfastCanceller()->restorable($customer, self::ROK)),
        );
    }

    public function testStillBookedNightIsNotOfferedBack(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane] = $this->pripravHotelSeSnidani(1);
        $this->koupSnidani($customer, $snidane);
        $this->writer()->save($customer, [$noc], self::ROK, true);

        // The night still covers it, so putting it back would only cancel it again.
        self::assertSame([], $this->breakfastCanceller()->restorable($customer, self::ROK));
    }

    public function testCancellingAgainReplacesTheRememberedSelection(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane] = $this->pripravHotelSeSnidani(1);
        [$jinaNoc, $jinaSnidane, $jinyNazev] = $this->pripravHotelSeSnidani(2);

        $this->koupSnidani($customer, $snidane);
        $this->writer()->save($customer, [$noc], self::ROK, true);

        // A later cancellation overwrites the row, so only the newest selection is offered.
        $this->koupSnidani($customer, $jinaSnidane);
        $this->writer()->save($customer, [$jinaNoc], self::ROK, true);
        $this->writer()->save($customer, [], self::ROK, true);

        self::assertSame(
            [$jinyNazev],
            array_values($this->breakfastCanceller()->restorable($customer, self::ROK)),
        );
    }

    public function testBuyingABreakfastUpdatesWhatWouldBeOfferedBack(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane] = $this->pripravHotelSeSnidani(1);
        [, $jinaSnidane, $jinyNazev] = $this->pripravHotelSeSnidani(2);

        // Cancelled once, so a snapshot exists naming the first breakfast.
        $this->koupSnidani($customer, $snidane);
        $this->writer()->save($customer, [$noc], self::ROK, true);
        $this->writer()->save($customer, [], self::ROK, true);

        // Buying a different one has to move the snapshot with it. Nothing else writes it
        // here — no further accommodation change happens — so only the purchase can.
        $this->koupSnidaniPresKosik($customer, $jinaSnidane);
        $this->smazSnidani($customer, $jinaSnidane);

        self::assertSame(
            [$jinyNazev],
            array_values($this->breakfastCanceller()->restorable($customer, self::ROK)),
        );
    }

    /**
     * Drops the purchase without touching accommodation, so only the snapshot remains.
     */
    private function smazSnidani(User $customer, int $variantId): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM shop_nakupy WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $customer->getId(),
                'variant'  => $variantId,
                'year'     => self::ROK,
            ],
        );
    }

    public function testRestorePutsTheCancelledBreakfastsBack(): void
    {
        $customer = $this->ucastnik();
        [$noc, $snidane, $nazev] = $this->pripravHotelSeSnidani(1);
        $this->koupSnidani($customer, $snidane);
        $this->writer()->save($customer, [$noc], self::ROK, true);
        $this->writer()->save($customer, [], self::ROK, true);

        $vraceno = $this->breakfastCanceller()->restore($customer, self::ROK);

        self::assertSame([$nazev], $vraceno);
        self::assertSame(1, $this->pocetNakupu($customer, $snidane), 'breakfast is bought again');
        // Nothing left to offer once it is back.
        self::assertSame([], $this->breakfastCanceller()->restorable($customer, self::ROK));

        // The row has to look like any other purchase: a bulk cancel filters on the tag
        // snapshot, and an order-less line is invisible to the cart.
        $radek = $this->connection()->fetchAssociative(
            'SELECT product_tags, order_id, cena_nakupni FROM shop_nakupy
             WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $customer->getId(),
                'variant'  => $snidane,
                'year'     => self::ROK,
            ],
        );
        self::assertIsArray($radek);
        self::assertContains(
            ProductTagCode::JIDLO->value,
            json_decode((string) $radek['product_tags'], true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertNotNull($radek['order_id']);
    }

    public function testRestoreDoesNothingWhenNothingWasCancelled(): void
    {
        $customer = $this->ucastnik();
        $this->pripravHotelSeSnidani(1);

        self::assertSame([], $this->breakfastCanceller()->restore($customer, self::ROK));
    }

    /**
     * Through the cart, so the OrderItem lifecycle listener fires as it does in production.
     */
    private function koupSnidaniPresKosik(User $customer, int $snidaneVariantId): void
    {
        $variant = $this->entityManager()->getRepository(ProductVariant::class)->find($snidaneVariantId);
        self::assertNotNull($variant);

        $cart = static::getContainer()->get(CartService::class)->getOrCreateCart($customer);
        static::getContainer()->get(CartService::class)->addItem($cart, $variant);
    }

    private function breakfastCanceller(): BreakfastCanceller
    {
        return static::getContainer()->get(BreakfastCanceller::class);
    }

    /**
     * The desk seats someone on a night the grid shows as full. Until now nothing on the
     * server enforced who may do that — the legacy button only unhid the night client-side.
     */
    public function testFullNightIsAllowedWithTheOverride(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 2);
        $customer = $this->ucastnik();
        $this->zaplnNoc(0, 2);
        $this->zaplnNoc(1, 2);

        $this->writer()->save(
            $customer,
            $this->idNoci(0, 1),
            self::ROK,
            false,
            mayOverbook: true,
        );

        self::assertSame(1, $this->pocetNoci($customer, 0));
        self::assertSame(1, $this->pocetNoci($customer, 1));
    }

    public function testOverrideDoesNotRelaxTheOtherRules(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->expectExceptionMessage(AccommodationWriter::ERROR_CONSECUTIVE_NIGHTS);

        $this->writer()->save(
            $customer,
            $this->idNoci(0, 2),
            self::ROK,
            false,
            mayOverbook: true,
        );
    }

    private function mealWriter(): \App\Service\MealWriter
    {
        return static::getContainer()->get(\App\Service\MealWriter::class);
    }

    /**
     * @return int[] meal variant ids the customer holds
     */
    private function drzenaJidla(User $customer): array
    {
        return array_map('intval', $this->connection()->fetchFirstColumn(
            "SELECT DISTINCT nakupy.variant_id
             FROM shop_nakupy AS nakupy
             JOIN product_variant AS varianty ON varianty.id = nakupy.variant_id
             JOIN product_product_tag AS vazba ON vazba.product_id = varianty.product_id
             JOIN product_tag AS tag ON tag.id = vazba.tag_id
             WHERE nakupy.id_uzivatele = :customer AND nakupy.rok = :year AND tag.code = 'jidlo'",
            [
                'customer' => $customer->getId(),
                'year'     => self::ROK,
            ],
        ));
    }

    public function testMealsAreSavedAsASet(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame([$snidaneId], $this->drzenaJidla($customer));
    }

    public function testMealsNotSentAreDropped(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->koupSnidani($customer, $snidaneId);

        $this->mealWriter()->save($customer, [], self::ROK);

        self::assertSame([], $this->drzenaJidla($customer));
    }

    public function testUnknownMealIsRefused(): void
    {
        $customer = $this->ucastnik();

        $this->expectExceptionMessage('není v nabídce');

        $this->mealWriter()->save($customer, [999999999], self::ROK);
    }

    /**
     * Legacy filtered hotel-covered breakfasts out of the request before writing. Here the
     * canceller decides afterwards, so ordering one the room already covers still drops it.
     */
    public function testBreakfastCoveredByAHotelNightIsDroppedAgain(): void
    {
        [$nocId, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->writer()->save($customer, [$nocId], self::ROK, true);

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame([], $this->drzenaJidla($customer));
    }

    /**
     * Totéž co u ubytování: zásoba na variantě je sice pro rozhodování o kapacitě
     * zastaralá, ale účastnický košík ji čte, takže ji prodej na pultu musí snížit.
     */
    public function testAdminMealSaleDecrementsTheVariantStock(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 3 WHERE id = :variant',
            [
                'variant' => $snidaneId,
            ],
        );

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame(2, $this->zbyvaNaVarianteId($snidaneId));
    }

    public function testAdminMealCancellationReturnsTheStock(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 3 WHERE id = :variant',
            [
                'variant' => $snidaneId,
            ],
        );
        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        $this->mealWriter()->save($customer, [], self::ROK);

        self::assertSame(3, $this->zbyvaNaVarianteId($snidaneId));
    }

    /**
     * Snídani krytou hotelovou nocí `BreakfastCanceller` smaže hned po zápisu. Zásoba se
     * proto musí vrátit — jinak každý takový zápis jeden kus tiše ztratí.
     */
    public function testBreakfastCancelledAsCoveredReturnsItsStock(): void
    {
        [$nocId, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 3 WHERE id = :variant',
            [
                'variant' => $snidaneId,
            ],
        );
        $this->writer()->save($customer, [$nocId], self::ROK, true);

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame([], $this->mealWriter()->heldMeals($customer, self::ROK), 'Krytá snídaně se ruší');
        self::assertSame(3, $this->zbyvaNaVarianteId($snidaneId), 'Zrušená snídaně musí zásobu vrátit');
    }

    /**
     * Zákazník může mít na jednu variantu víc řádků (v produkci 1554 případů). DELETE
     * smaže všechny, takže se musí vrátit tolik kusů, kolik jich zmizelo — ne jeden.
     */
    public function testCancellingReturnsAsManyPiecesAsRowsRemoved(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 9);
        $customer = $this->ucastnik();
        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);
        // Druhý řádek na tutéž noc, jak ho umí vyrobit legacy i košík. `zaplnNoc()` míří
        // na rodičovský produkt, kdežto writer zapisuje noc samotnou — tady je potřeba
        // duplikovat přesně ten řádek, který writer vytvořil.
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             SELECT id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, NOW()
             FROM shop_nakupy
             WHERE id_uzivatele = :c AND rok = :y AND variant_id = :v LIMIT 1',
            [
                'c' => $customer->getId(),
                'y' => self::ROK,
                'v' => $this->noci[0]->getId(),
            ],
        );
        $this->connection()->executeStatement(
            'UPDATE product_variant SET remaining_quantity = 7 WHERE id = :variant',
            [
                'variant' => $this->noci[0]->getId(),
            ],
        );

        self::assertSame(2, (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :c AND rok = :y AND variant_id = :v',
            [
                'c' => $customer->getId(),
                'y' => self::ROK,
                'v' => $this->noci[0]->getId(),
            ],
        ), 'Kontrola předpokladu: dva řádky na jednu noc');

        $this->writer()->save($customer, [], self::ROK, true);

        self::assertSame(9, $this->zbyvaNaVarianteId($this->noci[0]->getId()), 'Dva smazané řádky musí vrátit dva kusy');
    }

    /**
     * `null` znamená neomezeno; úprava zásoby to nesmí přepsat na číslo.
     */
    public function testUnlimitedStockStaysUnlimited(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);
        $this->mealWriter()->save($customer, [], self::ROK);

        self::assertNull($this->connection()->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :variant',
            [
                'variant' => $snidaneId,
            ],
        ));
    }

    /**
     * Rezervace drží postele pro orgy: účastníkovi se prodá jen veřejná část, obsluha na
     * rezervu dosáhne. Zápis to musí hlídat stejně jako mřížka, jinak by mřížka postel
     * schovala a writer ji přesto prodal.
     */
    public function testParticipantCannotBookIntoTheOrganizerReserve(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 3);
        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET reserved_for_organizers = 2
             WHERE kod_predmetu = (SELECT code FROM product_variant WHERE id = :variant)',
            [
                'variant' => $this->noci[0]->getId(),
            ],
        );
        $this->zaplnNoc(0, 1);

        $this->expectExceptionMessage('je plné');

        $this->writer()->save($this->ucastnik(), $this->idNoci(0), self::ROK, true);
    }

    /**
     * Organizátor na rezervu dosáhne — to je celý její smysl.
     */
    public function testOrganizerReachesTheReserve(): void
    {
        $this->pripravUbytovani(kusuVyrobeno: 3);
        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET reserved_for_organizers = 2
             WHERE kod_predmetu = (SELECT code FROM product_variant WHERE id = :variant)',
            [
                'variant' => $this->noci[0]->getId(),
            ],
        );
        $this->zaplnNoc(0, 1);

        $this->writer()->save($this->ucastnik(), $this->idNoci(0), self::ROK, true, jeOrganizator: true);

        self::assertSame(2, (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE rok = :y AND variant_id = :v',
            [
                'y' => self::ROK,
                'v' => $this->noci[0]->getId(),
            ],
        ), 'K zaplněné veřejné části přibyla noc z rezervy');
    }

    private function zbyvaNaVarianteId(int $variantId): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT remaining_quantity FROM product_variant WHERE id = :variant',
            [
                'variant' => $variantId,
            ],
        );
    }

    /**
     * Meals carry stock like anything else — 11 of the 12 meals in 2025 had a limit, even
     * though this year's are all unlimited. Legacy refused to sell past it and so must this.
     */
    public function testSoldOutMealIsRefused(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();

        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET kusu_vyrobeno = 1
             WHERE kod_predmetu = (SELECT code FROM product_variant WHERE id = :variant)',
            [
                'variant' => $snidaneId,
            ],
        );
        $this->koupSnidani($this->ucastnik(), $snidaneId);

        $this->expectExceptionMessage('vyprodané');

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);
    }

    /**
     * Deliberate difference from legacy, which filtered such a breakfast out and so never
     * remembered it. The desk did order it, so once the covering night goes away it comes back.
     */
    public function testBreakfastOrderedOntoACoveredMorningIsOfferedBackLater(): void
    {
        [$nocId, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        $this->writer()->save($customer, [$nocId], self::ROK, true);
        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        $this->writer()->save($customer, [], self::ROK, true);

        self::assertArrayHasKey(
            $snidaneId,
            static::getContainer()->get(BreakfastCanceller::class)->restorable($customer, self::ROK),
        );
    }

    public function testLastPortionIsStillSellable(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();

        $this->connection()->executeStatement(
            'UPDATE shop_predmety SET kusu_vyrobeno = 2
             WHERE kod_predmetu = (SELECT code FROM product_variant WHERE id = :variant)',
            [
                'variant' => $snidaneId,
            ],
        );
        $this->koupSnidani($this->ucastnik(), $snidaneId);

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame([$snidaneId], $this->drzenaJidla($customer));
    }

    /**
     * Every meal this year has an unlimited stock, so this is the branch production actually
     * takes — and the one an off-by-one in the capacity test would leave unnoticed.
     */
    public function testUnlimitedMealSellsWhateverIsAsked(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();
        foreach (range(1, 3) as $ignored) {
            $this->koupSnidani($this->ucastnik(), $snidaneId);
        }

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame([$snidaneId], $this->drzenaJidla($customer));
    }

    public function testSavingTheSameMealsTwiceDoesNotDuplicateThem(): void
    {
        [, $snidaneId] = $this->pripravHotelSeSnidani(0);
        $customer = $this->ucastnik();

        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);
        $this->mealWriter()->save($customer, [$snidaneId], self::ROK);

        self::assertSame(1, $this->pocetNakupu($customer, $snidaneId));
    }

    private function pocetNakupu(User $customer, int $variantId): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = :customer AND variant_id = :variant AND rok = :year',
            [
                'customer' => $customer->getId(),
                'variant'  => $variantId,
                'year'     => self::ROK,
            ],
        );
    }
}
