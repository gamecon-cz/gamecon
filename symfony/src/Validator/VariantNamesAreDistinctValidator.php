<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see VariantNamesAreDistinct
 */
class VariantNamesAreDistinctValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof VariantNamesAreDistinct) {
            throw new UnexpectedTypeException($constraint, VariantNamesAreDistinct::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        $variants = $value->getVariants();
        if ($variants->count() < 2) {
            return;
        }

        $seenNames = [];
        foreach ($variants as $variant) {
            $name = $variant->getName();
            if ($name === null) {
                $this->context->buildViolation($constraint->missingNameMessage)
                    ->atPath('variants')
                    ->addViolation();

                return;
            }

            // "m " and "M" read as one size in the picker. The column's czech_ci also equates
            // "patek" with "pátek", so a unique index would reject names this accepts.
            $comparableName = mb_strtolower(trim($name));
            if (isset($seenNames[$comparableName])) {
                $this->context->buildViolation($constraint->duplicateNameMessage)
                    ->setParameter('{{ name }}', $name)
                    ->atPath('variants')
                    ->addViolation();

                return;
            }
            $seenNames[$comparableName] = true;
        }
    }
}
