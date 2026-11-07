<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ProductVariant;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see DefaultVariantFollowsProduct
 */
class DefaultVariantFollowsProductValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof DefaultVariantFollowsProduct) {
            throw new UnexpectedTypeException($constraint, DefaultVariantFollowsProduct::class);
        }

        if (! $value instanceof ProductVariant) {
            throw new UnexpectedValueException($value, ProductVariant::class);
        }

        $product = $value->getProduct();
        if (! $value->hasOwnState()
            || $value->getCode() !== $product->getCode()
            || $value->getState() === $product->getState()
        ) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('state')
            ->addViolation();
    }
}
