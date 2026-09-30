<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use App\Repository\CancelledOrderItemRepository;
use App\Repository\OrderItemRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see PurchasedProductIsKept
 */
class PurchasedProductIsKeptValidator extends ConstraintValidator
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly CancelledOrderItemRepository $cancelledOrderItemRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof PurchasedProductIsKept) {
            throw new UnexpectedTypeException($constraint, PurchasedProductIsKept::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        if (! $this->orderItemRepository->hasPurchaseOf($value)
            && ! $this->cancelledOrderItemRepository->hasCancelledPurchaseOf($value)
        ) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ product }}', $value->getName())
            ->addViolation();
    }
}
