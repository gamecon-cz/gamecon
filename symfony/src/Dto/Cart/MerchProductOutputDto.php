<?php

declare(strict_types=1);

namespace App\Dto\Cart;

class MerchProductOutputDto
{
    public string $name;

    /**
     * Stabilní identita produktu (UNIQUE v databázi). Mřížka ji používá jako React key —
     * odvozovat klíč z varianty nejde, jejich pořadí není napevno dané.
     */
    public string $code;
    public string $description;

    /**
     * Velikosti (nebo jiné varianty) k výběru. Vždy aspoň jedna — produkt bez varianty se
     * do mřížky nedostane. Zásoba i strop jsou per varianta, ne per produkt.
     *
     * @var MerchVariantOutputDto[]
     */
    public array $variants = [];

    /**
     * List price before any role discount, so the UI can strike it through when
     * discountedPrice differs.
     */
    public string $price;
    public string $discountedPrice;

    /**
     * Součet přes všechny varianty — kolik kusů produktu zákazník letos má.
     */
    public int $purchasedQuantity;
    public bool $secondary;
    public bool $available;
}
