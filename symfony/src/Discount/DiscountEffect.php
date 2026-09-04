<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * What the rule does to the price.
 *
 * FIXED_AMOUNT exists because the organizer meal discount is a flat 30 Kč rather than
 * a percentage — the reason a percent-only column could not express these rules.
 */
enum DiscountEffect: string
{
    case FREE = 'free';
    case FIXED_AMOUNT = 'fixed_amount';
    case PERCENT = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::FREE         => 'Zdarma',
            self::FIXED_AMOUNT => 'Sleva pevnou částkou',
            self::PERCENT      => 'Sleva procentem',
        };
    }

    /**
     * One of these must be present. FIXED_AMOUNT accepts either a literal amount or
     * the name of a system setting to read it from, so the organizer meal discount can
     * follow SLEVA_ORGU_NA_JIDLO_CASTKA instead of freezing today's value.
     *
     * @return string[][]
     */
    public function requiredParameters(): array
    {
        return match ($this) {
            self::FREE         => [],
            self::FIXED_AMOUNT => [['amount', 'amountSetting']],
            self::PERCENT      => [['percent']],
        };
    }

    /**
     * @param float $price     the price before this rule
     * @param float $parameter amount in Kč, or percent, depending on the effect
     *
     * @return float the discount to subtract, never more than the price itself
     */
    public function discountFrom(float $price, float $parameter): float
    {
        $discount = match ($this) {
            self::FREE         => $price,
            self::FIXED_AMOUNT => $parameter,
            self::PERCENT      => $price * $parameter / 100,
        };

        return min($discount, $price);
    }
}
