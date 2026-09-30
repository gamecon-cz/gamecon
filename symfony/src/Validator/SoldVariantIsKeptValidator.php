<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ProductVariant;
use App\Repository\OrderItemRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see SoldVariantIsKept
 */
class SoldVariantIsKeptValidator extends ConstraintValidator
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof SoldVariantIsKept) {
            throw new UnexpectedTypeException($constraint, SoldVariantIsKept::class);
        }

        if (! $value instanceof ProductVariant) {
            throw new UnexpectedValueException($value, ProductVariant::class);
        }

        if ($this->orderItemRepository->soldVariantIds([(int) $value->getId()]) === []) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ variant }}', $value->getName())
            ->addViolation();
    }
}
