<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see VariantsAreNamedWhenSeveral
 */
class VariantsAreNamedWhenSeveralValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof VariantsAreNamedWhenSeveral) {
            throw new UnexpectedTypeException($constraint, VariantsAreNamedWhenSeveral::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        $variants = $value->getVariants();
        if ($variants->count() < 2) {
            return;
        }

        foreach ($variants as $variant) {
            if ($variant->getName() === null) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('variants')
                    ->addViolation();

                return;
            }
        }
    }
}
