<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see SoldVariantsAreKept
 */
class SoldVariantsAreKeptValidator extends ConstraintValidator
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof SoldVariantsAreKept) {
            throw new UnexpectedTypeException($constraint, SoldVariantsAreKept::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        $variants = $value->getVariants();
        // A new product's collection is not tracked yet, and it has nothing sold to lose.
        if (! $variants instanceof PersistentCollection) {
            return;
        }

        $removed = array_filter(
            $variants->getDeleteDiff(),
            static fn (ProductVariant $variant): bool => $variant->getId() !== null,
        );
        $soldIds = $this->orderItemRepository->soldVariantIds(array_map(
            static fn (ProductVariant $variant): int => (int) $variant->getId(),
            $removed,
        ));

        foreach ($removed as $variant) {
            if (in_array($variant->getId(), $soldIds, true)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ variant }}', $variant->getName())
                    ->atPath('variants')
                    ->addViolation();
            }
        }
    }
}
