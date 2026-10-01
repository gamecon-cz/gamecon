<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Only a product's single variant may go without a name, shown as the product itself; among
 * several, each needs a name of its own, or the size or night picker offers two alike.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class VariantNamesAreDistinct extends Constraint
{
    public string $missingNameMessage = 'Produkt s více variantami potřebuje u každé varianty název (velikost, noc…).';

    public string $duplicateNameMessage = 'Název varianty „{{ name }}" už má jiná varianta tohoto produktu.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
