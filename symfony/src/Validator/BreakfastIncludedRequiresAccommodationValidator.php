<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see BreakfastIncludedRequiresAccommodation
 */
class BreakfastIncludedRequiresAccommodationValidator extends ConstraintValidator
{
    private const ACCOMMODATION_TAG_CODE = 'ubytovani';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof BreakfastIncludedRequiresAccommodation) {
            throw new UnexpectedTypeException($constraint, BreakfastIncludedRequiresAccommodation::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        if (! $value->isBreakfastIncluded()) {
            return;
        }

        foreach ($value->getTags() as $tag) {
            if ($tag->getCode() === self::ACCOMMODATION_TAG_CODE) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('breakfastIncluded')
            ->addViolation();
    }
}
