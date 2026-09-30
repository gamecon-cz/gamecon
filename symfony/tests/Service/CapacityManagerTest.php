<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\RoleMeaning;
use App\Service\CapacityManager;
use App\Service\CurrentYearProviderInterface;
use App\Service\OperatorOverride;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\SoubeznaTransakce;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Gamecon\Tests\Factory\UserFactory;

class CapacityManagerTest extends AbstractDatabaseKernelTestCase
{
    private ?User $kupujici = null;

    private function capacityManager(): CapacityManager
    {
        return static::getContainer()->get(CapacityManager::class);
    }

    private function varianta(?int $kusuVyrobeno, ?int $rezervaProduktu = null, ?int $rezervaVarianty = null): ProductVariant
    {
        $kod = 'kapacita-' . uniqid();

        $product = new Product();
        $product->setName('Placka');
        $product->setCode($kod);
        $product->setCurrentPrice('50.00');
        $product->setDescription('');
        $product->setState(ProductStateEnum::PUBLIC);
        $product->setReservedForOrganizers($rezervaProduktu);
        $this->entityManager()->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setName('jedna velikost');
        $variant->setCode($kod);
        $variant->setCapacity($kusuVyrobeno);
        $variant->setReservedForOrganizers($rezervaVarianty);
        $variant->setPosition(0);
        $product->addVariant($variant);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return $variant;
    }

    private function uzivatel(): User
    {
        return UserFactory::createOne([
            UserEntityStructure::login => 'kapacita_' . uniqid(),
            UserEntityStructure::email => 'kapacita_' . uniqid() . '@example.invalid',
        ])->_save()->_real();
    }

    private function prodej(ProductVariant $variant, int $kusu, int $rok = ROCNIK): void
    {
        $this->kupujici ??= $this->uzivatel();

        for ($kus = 0; $kus < $kusu; ++$kus) {
            $this->connection()->executeStatement(
                'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
                 VALUES (:customer, :product, :variant, :year, 50, NOW())',
                [
                    'customer' => $this->kupujici->getId(),
                    'product'  => $variant->getProduct()->getId(),
                    'variant'  => $variant->getId(),
                    'year'     => $rok,
                ],
            );
        }
    }

    public function testRemainingIsCapacityMinusThisYearsPurchases(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 10);
        $this->prodej($variant, 3);
        $this->prodej($variant, 2, rok: ROCNIK - 1);

        self::assertSame(7, $this->capacityManager()->remaining($variant));
    }

    public function testNoCapacityMeansUnlimited(): void
    {
        $variant = $this->varianta(kusuVyrobeno: null);
        $this->prodej($variant, 3);

        self::assertNull($this->capacityManager()->remaining($variant));
        self::assertTrue($this->capacityManager()->hasAvailableCapacity($variant));
        self::assertFalse($this->capacityManager()->isSoldOut($variant));
        self::assertFalse($this->capacityManager()->isLowStock($variant, 10));
    }

    public function testOversoldStockGoesNegative(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 2);
        $this->prodej($variant, 3);

        self::assertSame(-1, $this->capacityManager()->remaining($variant));
        self::assertSame(0, $this->capacityManager()->availableQuantity($variant), 'Available is never negative');
        self::assertTrue($this->capacityManager()->isSoldOut($variant));
    }

    public function testRemainingForSeveralVariantsAtOnce(): void
    {
        $prodavana = $this->varianta(kusuVyrobeno: 5);
        $neomezena = $this->varianta(kusuVyrobeno: null);
        $this->prodej($prodavana, 1);

        self::assertSame(
            [
                $prodavana->getId() => 4,
                $neomezena->getId() => null,
            ],
            $this->capacityManager()->remainingByVariant([$prodavana, $neomezena]),
        );
    }

    public function testLowStock(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 5);

        self::assertTrue($this->capacityManager()->isLowStock($variant, 10));
        self::assertFalse($this->capacityManager()->isLowStock($variant, 3));

        $this->prodej($variant, 5);
        self::assertFalse($this->capacityManager()->isLowStock($variant, 10), 'Sold out is not low');
    }

    public function testOrganizerReserveIsHiddenFromParticipants(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 10, rezervaVarianty: 3);

        self::assertSame(7, $this->capacityManager()->availableQuantity($variant));
        self::assertSame(10, $this->capacityManager()->availableQuantity($variant, [RoleMeaning::ORGANIZATOR_ZDARMA]));
    }

    public function testReserveIsInheritedFromTheProduct(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 20, rezervaProduktu: 10);

        self::assertSame(10, $this->capacityManager()->availableQuantity($variant));
        self::assertSame(20, $this->capacityManager()->availableQuantity($variant, [RoleMeaning::VYPRAVEC]));
    }

    public function testAnyOrganizerRoleReachesTheReserve(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 5, rezervaVarianty: 5);

        foreach ([RoleMeaning::ORGANIZATOR_ZDARMA, RoleMeaning::VYPRAVEC, RoleMeaning::BRIGADNIK, RoleMeaning::ZAZEMI] as $role) {
            self::assertFalse($this->capacityManager()->isSoldOut($variant, [$role]), $role->name);
        }
        self::assertFalse($this->capacityManager()->isSoldOut($variant, [RoleMeaning::PRIHLASEN, RoleMeaning::BRIGADNIK]));
        self::assertTrue($this->capacityManager()->isSoldOut($variant));
        self::assertTrue($this->capacityManager()->isSoldOut($variant, [RoleMeaning::PRIHLASEN]));
    }

    public function testLockForSaleLetsTheLastPieceGo(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 2);
        $this->prodej($variant, 1);

        $this->capacityManager()->lockForSale($variant);

        $this->addToAssertionCount(1);
    }

    public function testLockForSaleRefusesMoreThanRemains(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 2);
        $this->prodej($variant, 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~kapacita~');

        $this->capacityManager()->lockForSale($variant, 2);
    }

    public function testParticipantCannotBuyIntoTheReserve(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 3, rezervaVarianty: 2);
        $this->prodej($variant, 1);

        $this->expectException(\RuntimeException::class);

        $this->capacityManager()->lockForSale($variant);
    }

    public function testOrganizerAndDeskOverrideReachTheReserve(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 3, rezervaVarianty: 2);
        $this->prodej($variant, 1);

        $this->capacityManager()->lockForSale($variant, 2, [RoleMeaning::ORGANIZATOR_ZDARMA]);
        $this->capacityManager()->lockForSale($variant, 2, override: OperatorOverride::deskSale($this->uzivatel()));

        $this->addToAssertionCount(2);
    }

    public function testLockForSaleWithUnlimitedCapacityAlwaysPasses(): void
    {
        $variant = $this->varianta(kusuVyrobeno: null);

        $this->capacityManager()->lockForSale($variant, 1000);

        $this->addToAssertionCount(1);
    }

    /**
     * Without a transaction the row lock ends with the SELECT, and two buyers could both
     * count the last piece as free.
     */
    public function testLockForSaleOutsideATransactionIsAProgrammingError(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 5);
        $this->connection()->rollBack();

        $this->expectException(\LogicException::class);

        $this->capacityManager()->lockForSale($variant);
    }

    /**
     * Two buyers race for the last piece. The second has already read something in its
     * transaction, so a plain COUNT would see its old snapshot and miss the first buyer's row.
     */
    public function testSecondBuyerSeesTheFirstBuyersCommittedPurchase(): void
    {
        $this->sPotvrzenymiVariantami([1], function (array $varianty, callable $noveSpojeni): void {
            [$druheSpojeni, $druhyKupujici] = $noveSpojeni();
            $druheSpojeni->beginTransaction();
            $druheSpojeni->fetchOne('SELECT COUNT(*) FROM shop_nakupy');

            $this->nakupNa($this->connection(), $varianty[0]);

            $this->expectException(\RuntimeException::class);
            $druhyKupujici->lockForSale($varianty[0]);
        });
    }

    /**
     * Under REPEATABLE READ the stock count also locks the index gap, so two buyers of
     * different products would each wait on the other's insert — a deadlock.
     */
    public function testBuyersOfDifferentProductsDoNotBlockEachOther(): void
    {
        $this->sPotvrzenymiVariantami([5, 5], function (array $varianty, callable $noveSpojeni): void {
            [$spojeniA, $kupujiciA] = $noveSpojeni();
            [$spojeniB, $kupujiciB] = $noveSpojeni();
            // A throwaway connection; a lock wait would otherwise hang the test for 50 s.
            $spojeniA->executeStatement('SET SESSION innodb_lock_wait_timeout = 2');

            $kupujiciA->beginSaleTransaction();
            $kupujiciB->beginSaleTransaction();
            $kupujiciA->lockForSale($varianty[0]);
            $kupujiciB->lockForSale($varianty[1]);
            $this->nakupNa($spojeniA, $varianty[0]);
            $this->nakupNa($spojeniB, $varianty[1]);
            $spojeniA->commit();
            $spojeniB->commit();

            $this->addToAssertionCount(1);
        });
    }

    /**
     * The other buyer takes the lower variant, then the higher one. Asked for them the other way
     * round, this one must wait for the lower first rather than take the higher and deadlock.
     */
    public function testLockInOrderQueuesBehindABuyerOfTheSameVariantsInReverse(): void
    {
        $this->sPotvrzenymiVariantami([5, 5], function (array $varianty): void {
            $idVariant = array_map(static fn (ProductVariant $variant): int => (int) $variant->getId(), $varianty);
            sort($idVariant);
            [$nizsi, $vyssi] = $idVariant;
            $souper = SoubeznaTransakce::spust($this->connection(), [
                ['sql', "SELECT id FROM product_variant WHERE id = {$nizsi} FOR UPDATE"],
                ['hlasim', 'drzi nizsi'],
                ['cekej', 700],
                ['sql', "SELECT id FROM product_variant WHERE id = {$vyssi} FOR UPDATE"],
                ['cekej', 300],
            ]);

            $chybaZamku = null;
            $this->capacityManager()->beginSaleTransaction();
            try {
                $this->capacityManager()->lockInOrder([$vyssi, $nizsi]);
            } catch (\Throwable $chyba) {
                $chybaZamku = $chyba;
            } finally {
                $this->connection()->rollBack();
            }

            self::assertSame('hotovo', $souper->dokonci());
            self::assertNull($chybaZamku, (string) $chybaZamku?->getMessage());
        });
    }

    /**
     * Races need rows other connections can see, so the variants are committed and removed
     * again afterwards.
     *
     * @param list<int|null>                                                                   $kapacity
     * @param callable(ProductVariant[], callable(): array{Connection, CapacityManager}): void $scenar
     */
    private function sPotvrzenymiVariantami(array $kapacity, callable $scenar): void
    {
        $varianty = array_map(fn (?int $kusu): ProductVariant => $this->varianta(kusuVyrobeno: $kusu), $kapacity);
        $this->connection()->commit();

        $spojeni = [];
        try {
            $scenar($varianty, function () use (&$spojeni): array {
                $dalsi = DriverManager::getConnection($this->connection()->getParams());
                $spojeni[] = $dalsi;

                return [$dalsi, new CapacityManager($dalsi, static::getContainer()->get(CurrentYearProviderInterface::class))];
            });
        } finally {
            foreach ($spojeni as $dalsi) {
                if ($dalsi->isTransactionActive()) {
                    $dalsi->rollBack();
                }
                $dalsi->close();
            }
            foreach ($varianty as $variant) {
                $this->connection()->executeStatement('DELETE FROM shop_nakupy WHERE variant_id = :id', [
                    'id' => $variant->getId(),
                ]);
                $this->connection()->executeStatement('DELETE FROM product_variant WHERE id = :id', [
                    'id' => $variant->getId(),
                ]);
                $this->connection()->executeStatement('DELETE FROM shop_predmety WHERE id_predmetu = :id', [
                    'id' => $variant->getProduct()->getId(),
                ]);
            }
            $this->connection()->beginTransaction();
        }
    }

    private function nakupNa(Connection $spojeni, ProductVariant $variant): void
    {
        $spojeni->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :product, :variant, :year, 50, NOW())',
            [
                'customer' => \Uzivatel::SYSTEM,
                'product'  => $variant->getProduct()->getId(),
                'variant'  => $variant->getId(),
                'year'     => ROCNIK,
            ],
        );
    }

    public function testCapacityInfo(): void
    {
        $variant = $this->varianta(kusuVyrobeno: 20, rezervaVarianty: 5);
        $this->prodej($variant, 2);

        self::assertSame(
            [
                'remaining'                => 18,
                'reserved'                 => 5,
                'availableForParticipants' => 13,
                'unlimited'                => false,
            ],
            $this->capacityManager()->getCapacityInfo($variant),
        );
    }
}
