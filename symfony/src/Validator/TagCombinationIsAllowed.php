<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A product carries exactly one category tag, and any sub-tag it carries must sit on the
 * category that sub-tag belongs to.
 *
 * Without this the tags are just a free-form set, and a product can end up describing two
 * things it cannot both be — the failure is silent, because every consumer filters by one
 * tag and simply never sees the contradiction.
 *
 * Applied as a class-level constraint on App\Entity\Product.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TagCombinationIsAllowed extends Constraint
{
    public string $categoryMessage = 'Produkt musí mít právě jednu kategorii, má jich {{ count }}.';

    public string $subTagMessage = 'Značka „{{ subTag }}" patří jen ke kategorii „{{ category }}".';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
