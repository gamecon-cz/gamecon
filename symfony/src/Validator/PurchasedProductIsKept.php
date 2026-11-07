<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Purchases and cancelled purchases keep pointing at their product, so one ever bought is
 * retired, not deleted.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PurchasedProductIsKept extends Constraint
{
    public string $message = 'Produkt „{{ product }}" nejde smazat, už se prodával. Místo smazání ho přepni do stavu Vyřazený.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
