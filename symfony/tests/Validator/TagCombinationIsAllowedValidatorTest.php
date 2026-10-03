<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Enum\ProductTagCode;
use App\Validator\TagCombinationIsAllowed;
use App\Validator\TagCombinationIsAllowedValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class TagCombinationIsAllowedValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TagCombinationIsAllowedValidator
    {
        return new TagCombinationIsAllowedValidator();
    }

    private function product(ProductTagCode ...$kody): Product
    {
        $product = new Product();
        foreach ($kody as $kod) {
            $tag = new ProductTag();
            $tag->setCode($kod->value);
            $product->addTag($tag);
        }

        return $product;
    }

    public function testOneCategoryIsValid(): void
    {
        $this->validator->validate($this->product(ProductTagCode::UBYTOVANI), new TagCombinationIsAllowed());

        $this->assertNoViolation();
    }

    public function testSubTagOnItsOwnCategoryIsValid(): void
    {
        $this->validator->validate(
            $this->product(ProductTagCode::PREDMET, ProductTagCode::MIKINA),
            new TagCombinationIsAllowed(),
        );

        $this->assertNoViolation();
    }

    public function testSubTagOnTheWrongCategoryIsRefused(): void
    {
        $constraint = new TagCombinationIsAllowed();

        $this->validator->validate(
            $this->product(ProductTagCode::UBYTOVANI, ProductTagCode::MIKINA),
            $constraint,
        );

        $this->buildViolation($constraint->subTagMessage)
            ->setParameter('{{ subTag }}', 'mikina')
            ->setParameter('{{ category }}', 'predmet')
            ->atPath('property.path.tags')
            ->assertRaised();
    }

    public function testTwoCategoriesAreRefused(): void
    {
        $constraint = new TagCombinationIsAllowed();

        $this->validator->validate(
            $this->product(ProductTagCode::PREDMET, ProductTagCode::UBYTOVANI),
            $constraint,
        );

        $this->buildViolation($constraint->categoryMessage)
            ->setParameter('{{ count }}', '2')
            ->atPath('property.path.tags')
            ->assertRaised();
    }

    public function testNoCategoryIsRefused(): void
    {
        $constraint = new TagCombinationIsAllowed();

        $this->validator->validate($this->product(ProductTagCode::MIKINA), $constraint);

        $this->buildViolation($constraint->categoryMessage)
            ->setParameter('{{ count }}', '0')
            ->atPath('property.path.tags')
            ->assertRaised();
    }
}
