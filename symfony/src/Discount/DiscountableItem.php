<?php

declare(strict_types=1);

namespace App\Discount;

use App\Enum\ProductTagCode;

/**
 * One thing in the cart, in the only terms the discount rules care about.
 *
 * Not a Product or an OrderItem, so the calculator works the same whether it is called
 * from the new cart or from legacy Cenik, which has an array row and no entities.
 */
final readonly class DiscountableItem
{
    public function __construct(
        /** Whatever the caller uses to recognise this item again in the results. */
        public int|string $key,
        public string $productCode,
        public float $price,
        /**
         * @var ProductTagCode[]
         */
        public array $tags,
        /**
         * Accommodation day 0–4, null for everything else.
         */
        public ?int $accommodationDay = null,
    ) {
    }

    public function hasTag(ProductTagCode $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function matches(DiscountParameters $parameters): bool
    {
        return match ($parameters->scope) {
            DiscountScope::CODE_CONTAINS => $parameters->codeFragment !== null
                && str_contains(mb_strtolower($this->productCode), mb_strtolower($parameters->codeFragment)),
            DiscountScope::TAG, DiscountScope::TAG_CHEAPEST => $parameters->tag !== null
                && $this->hasTag($parameters->tag),
            DiscountScope::TAG_AND_DAY => $parameters->tag !== null
                && $this->hasTag($parameters->tag)
                && $this->accommodationDay === $parameters->day,
        };
    }
}
