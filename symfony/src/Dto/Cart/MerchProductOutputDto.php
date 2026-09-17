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
     * Cena podle pořadí kusu — „první zdarma, druhý za polovinu, další za plnou".
     * Frontend si po přidání do košíku dopočítá cenu dalšího kusu sám, bez dotazu.
     *
     * @var array<int, array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null}>
     */
    public array $priceSteps = [];

    /**
     * Součet přes všechny varianty — kolik kusů produktu zákazník letos má.
     */
    public int $purchasedQuantity;
    public bool $secondary;
    public bool $available;
}
