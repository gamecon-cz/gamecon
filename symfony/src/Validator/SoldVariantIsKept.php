<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @see SoldVariantsAreKept for why; this one guards deleting the variant on its own.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SoldVariantIsKept extends Constraint
{
    public string $message = 'Variantu „{{ variant }}" nejde smazat, už se prodávala.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
