<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\AccommodationWriter;
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
}
