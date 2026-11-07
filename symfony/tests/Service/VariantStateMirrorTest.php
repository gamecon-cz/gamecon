<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductStateEnum;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Admins change a product's offer on the product, while the cart and the variant view read the
 * variant; the product's default variant has to follow it, the others keep their own state.
 */
class VariantStateMirrorTest extends AbstractDatabaseKernelTestCase
{
    private int $predmet;

    private int $typPokoje;

    protected function setUp(): void
    {
        parent::setUp();

        $kod = 'zrcadlo-' . uniqid();
        $this->predmet = $this->vlozProdukt('Placka zrcadlo', $kod, ProductStateEnum::PUBLIC);
        $this->vlozVariantu($this->predmet, null, $kod, ProductStateEnum::PUBLIC);
        $this->typPokoje = $this->vlozProdukt('Postel zrcadlo', $kod . '-typ', ProductStateEnum::SUSPENDED);
        $this->vlozVariantu($this->typPokoje, 'pátek', $kod . '-pa', ProductStateEnum::PUBLIC);
    }

    public function testPausedProductPausesItsDefaultVariant(): void
    {
        $predmet = $this->entityManager()->find(Product::class, $this->predmet);
        $varianta = $this->varianta($this->predmet);

        $predmet->setState(ProductStateEnum::SUSPENDED);
        $this->entityManager()->flush();

        self::assertSame(ProductStateEnum::SUSPENDED->value, $this->stavVarianty($this->predmet));
        self::assertSame(ProductStateEnum::SUSPENDED, $varianta->getState(), 'Načtená varianta nesmí zůstat se starým stavem');
    }

    /**
     * A past year archives the product; that is history, not an item taken off the offer.
     */
    public function testArchivedProductKeepsItsVariantAsItWas(): void
    {
        $this->entityManager()->find(Product::class, $this->predmet)->archive();
        $this->entityManager()->flush();

        self::assertSame(ProductStateEnum::PUBLIC->value, $this->stavVarianty($this->predmet));
    }

    /**
     * A room type nobody buys stays suspended while its nights are on sale.
     */
    public function testNightKeepsItsOwnStateWhenItsRoomTypeChanges(): void
    {
        $typPokoje = $this->entityManager()->find(Product::class, $this->typPokoje);

        $typPokoje->setState(ProductStateEnum::RETIRED);
        $this->entityManager()->flush();

        self::assertSame(ProductStateEnum::PUBLIC->value, $this->stavVarianty($this->typPokoje));
    }

    private function vlozProdukt(string $nazev, string $kod, ProductStateEnum $stav): int
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_predmety (nazev, kod_predmetu, cena_aktualni, stav, popis) VALUES (:nazev, :kod, 400, :stav, \'\')',
            [
                'nazev' => $nazev,
                'kod'   => $kod,
                'stav'  => $stav->value,
            ],
        );

        return (int) $this->connection()->lastInsertId();
    }

    private function vlozVariantu(int $produkt, ?string $nazev, string $kod, ProductStateEnum $stav): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO product_variant (product_id, name, code, capacity, position, state) VALUES (:produkt, :nazev, :kod, 5, 0, :stav)',
            [
                'produkt' => $produkt,
                'nazev'   => $nazev,
                'kod'     => $kod,
                'stav'    => $stav->value,
            ],
        );
    }

    private function varianta(int $produkt): ProductVariant
    {
        return $this->entityManager()->getRepository(ProductVariant::class)->findOneBy([
            'product' => $produkt,
        ]);
    }

    private function stavVarianty(int $produkt): int
    {
        return (int) $this->connection()->fetchOne('SELECT state FROM product_variant WHERE product_id = :produkt', [
            'produkt' => $produkt,
        ]);
    }
}
