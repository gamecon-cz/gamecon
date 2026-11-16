<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Enum\ProductTagCode;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;

readonly class AccommodationAvailability
{
    public function __construct(
        private ProductRepository $productRepository,
        private OrderItemRepository $orderItemRepository,
    ) {
    }

    /**
     * Klíčem je **kód varianty**, ne id: podle kódu noci se dostupnost čte i na straně košíku.
     *
     * @return array<string, AccommodationNightAvailability>
     */
    public function proZakaznika(int $year, bool $jeOrganizator): array
    {
        $varianty = $this->varianty();
        if ($varianty === []) {
            return [];
        }

        $ids = [];
        $kody = [];
        foreach ($varianty as $variant) {
            $ids[] = (int) $variant->getId();
            $kody[] = (string) $variant->getCode();
        }

        $prodano = $this->orderItemRepository->countSoldByVariant($ids, $year);
        $kapacity = $this->productRepository->capacityByVariantCode($kody);

        $dostupnost = [];
        foreach ($varianty as $variant) {
            $id = (int) $variant->getId();
            $kod = (string) $variant->getCode();
            $noc = $kapacity[$kod] ?? null;

            $dostupnost[$kod] = $this->noc(
                vyrobeno: $noc['vyrobeno'] ?? null,
                nabizeno: $noc['nabizeno'] ?? false,
                rezervovano: $noc['rezervovano'] ?? null,
                prodano: $prodano[$id] ?? 0,
                jeOrganizator: $jeOrganizator,
            );
        }

        return $dostupnost;
    }

    /**
     * Odložené postele účastník nevidí, organizátor ano — stejné pravidlo jako u merche
     * v `CapacityManager::lockForSale()`. Vlastní noci zákazníka jsou v `$prodano` jako každé
     * jiné, takže zbytek je to, co zbývá ostatním: po zaškrtnutí klesne o jedna.
     *
     * Pod nulu jde jen skutečné přeprodání (pult smí přeplnit); rezerva ubírá nanejvýš do nuly,
     * aby překlep v ní nevypadal jako přeprodaná noc.
     */
    private function noc(
        ?int $vyrobeno,
        bool $nabizeno,
        ?int $rezervovano,
        int $prodano,
        bool $jeOrganizator,
    ): AccommodationNightAvailability {
        $zbyva = null;
        if ($vyrobeno !== null) {
            $zbytekSkladu = $vyrobeno - $prodano;
            $zbyva = $zbytekSkladu < 0
                ? $zbytekSkladu
                : max(0, $zbytekSkladu - ($jeOrganizator ? 0 : ($rezervovano ?? 0)));
        }

        return new AccommodationNightAvailability(
            remaining: $zbyva,
            offered: $nabizeno,
            reservedForOrganizers: $rezervovano,
        );
    }

    /**
     * @return ProductVariant[]
     */
    private function varianty(): array
    {
        $varianty = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::UBYTOVANI) as $product) {
            foreach ($product->getVariants() as $variant) {
                if ($variant->getId() !== null) {
                    $varianty[] = $variant;
                }
            }
        }

        return $varianty;
    }
}
