<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Only a product's single variant may go without a name, shown as the product itself; among
 * several, a nameless one is a blank option in the size or night picker.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class VariantsAreNamedWhenSeveral extends Constraint
{
    public string $message = 'Produkt s více variantami potřebuje u každé varianty název (velikost, noc…).';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
