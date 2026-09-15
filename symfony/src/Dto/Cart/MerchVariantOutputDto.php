<?php

declare(strict_types=1);

namespace App\Dto\Cart;

/**
 * Jedna volitelná varianta produktu — typicky velikost. Zásoba i strop jsou per varianta,
 * protože ponožky 38-39 a 42-45 se doprodávají nezávisle.
 */
class MerchVariantOutputDto
{
    public int $id;

    /**
     * Popisek do přepínače („38-39", „XL"). U produktu s jedinou variantou ho UI skryje.
     */
    public string $name;

    /**
     * Kolik kusů téhle varianty už zákazník letos má — mřížka tím předvyplní počet.
     */
    public int $purchasedQuantity;

    /**
     * Strop včetně už koupených kusů, nebo null při neomezené zásobě.
     */
    public ?int $maxQuantity;
}
