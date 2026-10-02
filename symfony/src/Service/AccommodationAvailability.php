<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Entity\User;
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
     * Klíčem je **kód varianty**, ne id. Kód je to jediné, co obě strany umí odvodit:
     * varianta nese týž `kod_predmetu` jako řádek své noci, kdežto `product_id` ukazuje na
     * nedělní noc, která variantám dělá rodiče.
     *
     * @return array<string, AccommodationNightAvailability>
     */
    public function proZakaznika(User $customer, int $year, bool $jeOrganizator): array
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
        $drzeno = $this->orderItemRepository->countHeldByCustomer($ids, $customer, $year);
        $kapacity = $this->productRepository->capacityByVariantCode($kody);

        $dostupnost = [];
        foreach ($varianty as $variant) {
            $id = (int) $variant->getId();
            $kod = (string) $variant->getCode();
            $noc = $kapacity[$kod] ?? null;

            $dostupnost[$kod] = $this->noc(
                vyrobeno: $noc['vyrobeno'] ?? null,
                // Stav noci, ne typu pokoje: ten zůstává pozastavený, i když se jeho noci prodávají.
                nabizeno: $noc['nabizeno'] ?? false,
                rezervovano: $noc['rezervovano'] ?? null,
                prodano: $prodano[$id] ?? 0,
                drzeno: $drzeno[$id] ?? 0,
                jeOrganizator: $jeOrganizator,
            );
        }

        return $dostupnost;
    }

    /**
     * Odložené postele účastník nevidí, organizátor ano — stejné pravidlo jako u merche
     * v `CapacityManager::lockForSale()`. Vlastní už koupené noci se přičítají zpátky, jinak
     * by si je zákazník nemohl odškrtnout.
     */
    private function noc(
        ?int $vyrobeno,
        bool $nabizeno,
        ?int $rezervovano,
        int $prodano,
        int $drzeno,
        bool $jeOrganizator,
    ): AccommodationNightAvailability {
        $zbyva = $vyrobeno === null
            ? null
            : max(0, $vyrobeno
                - $prodano
                - ($jeOrganizator ? 0 : ($rezervovano ?? 0))
                + $drzeno);

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
