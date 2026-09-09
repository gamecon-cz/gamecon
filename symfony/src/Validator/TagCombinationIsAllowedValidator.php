<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Product;
use App\Enum\ProductTagCode;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see TagCombinationIsAllowed
 */
class TagCombinationIsAllowedValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof TagCombinationIsAllowed) {
            throw new UnexpectedTypeException($constraint, TagCombinationIsAllowed::class);
        }

        if (! $value instanceof Product) {
            throw new UnexpectedValueException($value, Product::class);
        }

        $kody = [];
        foreach ($value->getTags() as $tag) {
            $kod = ProductTagCode::tryFrom((string) $tag->getCode());
            if ($kod !== null) {
                $kody[] = $kod;
            }
        }

        $kategorie = array_values(array_filter($kody, static fn (ProductTagCode $kod): bool => $kod->isCategory()));
        if (count($kategorie) !== 1) {
            $this->context->buildViolation($constraint->categoryMessage)
                ->setParameter('{{ count }}', (string) count($kategorie))
                ->atPath('tags')
                ->addViolation();

            // Which category a sub-tag needs is unanswerable until there is exactly one.
            return;
        }

        foreach ($kody as $kod) {
            $vyzadovana = $kod->requiredCategory();
            if ($vyzadovana !== null && $vyzadovana !== $kategorie[0]) {
                $this->context->buildViolation($constraint->subTagMessage)
                    ->setParameter('{{ subTag }}', $kod->value)
                    ->setParameter('{{ category }}', $vyzadovana->value)
                    ->atPath('tags')
                    ->addViolation();
            }
        }
    }
}
