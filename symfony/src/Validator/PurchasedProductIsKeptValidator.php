<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CancelledOrderItemRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductVariantRepository;
use App\Service\PurchasedVariants;
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
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly PurchasedVariants $purchasedVariants,
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
            && ! $this->hasBoughtVariant($value)
        ) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ product }}', $value->getName())
            ->addViolation();
    }

    private function hasBoughtVariant(Product $product): bool
    {
        $variantIds = array_map(
            static fn (ProductVariant $variant): int => (int) $variant->getId(),
            $product->getVariants()->toArray(),
        );
        // A size's or night's own catalog row from the legacy layout has no variants of its own;
        // the item it stands for is the variant with its code, whose purchases point at the model.
        $variantOfThisRow = $this->productVariantRepository->findOneBy([
            'code' => $product->getCode(),
        ]);
        if ($variantOfThisRow !== null) {
            $variantIds[] = (int) $variantOfThisRow->getId();
        }

        return $this->purchasedVariants->among($variantIds) !== [];
    }
}
