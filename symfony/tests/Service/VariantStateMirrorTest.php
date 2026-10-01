<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Admins and the import still change a night's offer through its own legacy catalog row, while
 * the cart and the variant view read the variant. The variant has to follow that row.
 */
class VariantStateMirrorTest extends AbstractDatabaseKernelTestCase
{
    private int $typPokoje;

    private int $noc;

    protected function setUp(): void
    {
        parent::setUp();

        $kod = 'zrcadlo-' . uniqid();
        $this->connection()->executeStatement(
            'INSERT INTO shop_predmety (nazev, kod_predmetu, cena_aktualni, stav, popis)
             VALUES (:nazev, :kod, 400, :stav, \'\')',
            [
                'nazev' => 'Postel zrcadlo',
                'kod'   => $kod . '-typ',
                'stav'  => ProductStateEnum::SUSPENDED->value,
            ],
        );
        $this->typPokoje = (int) $this->connection()->lastInsertId();
        $this->connection()->executeStatement(
            'INSERT INTO shop_predmety (nazev, kod_predmetu, cena_aktualni, stav, popis)
             VALUES (:nazev, :kod, 400, :stav, \'\')',
            [
                'nazev' => 'Postel zrcadlo pátek',
                'kod'   => $kod . '-pa',
                'stav'  => ProductStateEnum::PUBLIC->value,
            ],
        );
        $this->noc = (int) $this->connection()->lastInsertId();
        $this->connection()->executeStatement(
            'INSERT INTO product_variant (product_id, name, code, capacity, accommodation_day, position, state)
             VALUES (:typ, \'pátek\', :kod, 5, 2, 0, :stav)',
            [
                'typ'  => $this->typPokoje,
                'kod'  => $kod . '-pa',
                'stav' => ProductStateEnum::PUBLIC->value,
            ],
        );
    }

    public function testNightPausedOnItsRowIsPausedOnItsVariant(): void
    {
        $noc = $this->entityManager()->find(Product::class, $this->noc);
        $varianta = $this->varianta();

        $noc->setState(ProductStateEnum::SUSPENDED);
        $this->entityManager()->flush();

        self::assertSame(ProductStateEnum::SUSPENDED->value, $this->stavVarianty());
        self::assertSame(ProductStateEnum::SUSPENDED, $varianta->getState(), 'Načtená varianta nesmí zůstat se starým stavem');
    }

    public function testNightDroppedFromTheCatalogueIsRetiredAndComesBack(): void
    {
        $noc = $this->entityManager()->find(Product::class, $this->noc);

        $noc->archive();
        $this->entityManager()->flush();
        self::assertSame(ProductStateEnum::RETIRED->value, $this->stavVarianty());

        $noc->restore();
        $this->entityManager()->flush();
        self::assertSame(ProductStateEnum::PUBLIC->value, $this->stavVarianty());
    }

    /**
     * A past year archives the room type and its nights together; that is history, not a
     * night taken off the offer, and the variant keeps what the night was.
     */
    public function testArchivingTheWholeRoomTypeKeepsTheNightAsItWas(): void
    {
        $this->entityManager()->find(Product::class, $this->typPokoje)->archive();
        $this->entityManager()->find(Product::class, $this->noc)->archive();
        $this->entityManager()->flush();

        self::assertSame(ProductStateEnum::PUBLIC->value, $this->stavVarianty());
    }

    private function varianta(): ProductVariant
    {
        return $this->entityManager()->getRepository(ProductVariant::class)->findOneBy([
            'product' => $this->typPokoje,
        ]);
    }

    private function stavVarianty(): int
    {
        return (int) $this->connection()->fetchOne('SELECT state FROM product_variant WHERE product_id = :typ', [
            'typ' => $this->typPokoje,
        ]);
    }
}
