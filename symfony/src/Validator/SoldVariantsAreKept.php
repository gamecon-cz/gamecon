<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Deleting a variant detaches its purchases (`variant_id` goes NULL), which drops them out
 * of its stock count and per-variant reports, so a sold variant stays.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SoldVariantsAreKept extends Constraint
{
    public string $message = 'Variantu „{{ variant }}" nejde odebrat, už se prodávala.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
