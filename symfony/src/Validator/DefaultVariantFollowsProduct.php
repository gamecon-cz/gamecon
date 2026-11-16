<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The variant sharing its product's code is the product as the cart sees it; an admin edit of
 * the product copies the product's state onto it (VariantStateMirror), so a state of its own
 * would not last.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DefaultVariantFollowsProduct extends Constraint
{
    public string $message = 'Varianta s kódem produktu má stav produktu; změň stav produktu.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
