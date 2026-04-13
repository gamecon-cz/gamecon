<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that a Product can only have breakfastIncluded=true when it also
 * carries the 'ubytovani' category tag.
 *
 * breakfast_included is an attribute that means "price already includes
 * breakfast" and only makes sense for accommodation products (specifically
 * hotel rooms). Setting it on a non-accommodation product is nonsensical and
 * would confuse the meal-voucher / breakfast-cancellation logic in
 * ShopUbytovani and the stravenky report.
 *
 * Applied as a class-level constraint on App\Entity\Product.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class BreakfastIncludedRequiresAccommodation extends Constraint
{
    public string $message = 'Snídani v ceně lze zapnout jen u ubytování.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
