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
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
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
        $snidane->setCode($snidaneProdukt->getCode() . '-r');
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

        $this->expectExceptionMessage(AccommodationWriter::CHYBA_NAVAZUJICI_NOCI);

        $this->writer()->save($customer, $this->idNoci(0, 2), self::ROK, false);
    }

    public function testSingleNightIsRefusedWithoutThePermission(): void
    {
        $this->pripravUbytovani();
        $customer = $this->ucastnik();

        $this->expectExceptionMessage(AccommodationWriter::CHYBA_MINIMALNE_DVE_NOCI);

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

        $this->expectExceptionMessage('obsazené');

        $this->writer()->save($customer, $this->idNoci(0), self::ROK, true);
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
            $this->breakfastCanceller()->restorable($customer, self::ROK),
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
            $this->breakfastCanceller()->restorable($customer, self::ROK),
        );
    }

    private function breakfastCanceller(): BreakfastCanceller
    {
        return static::getContainer()->get(BreakfastCanceller::class);
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
