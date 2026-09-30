<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use App\Entity\ProductVariant;
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
            && ! $this->hasSoldVariant($value)
        ) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ product }}', $value->getName())
            ->addViolation();
    }

    /**
     * A purchase names its variant's product only by `variant_id`: sizes were moved under their
     * group's owner while their purchases kept the size's own catalog row.
     */
    private function hasSoldVariant(Product $product): bool
    {
        return $this->orderItemRepository->soldVariantIds(array_map(
            static fn (ProductVariant $variant): int => (int) $variant->getId(),
            $product->getVariants()->toArray(),
        )) !== [];
    }
}
