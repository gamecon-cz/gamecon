<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Validator\BreakfastIncludedRequiresAccommodation;
use App\Validator\BreakfastIncludedRequiresAccommodationValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class BreakfastIncludedRequiresAccommodationValidatorTest extends TestCase
{
    private MockObject $context;

    private BreakfastIncludedRequiresAccommodationValidator $validator;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator = new BreakfastIncludedRequiresAccommodationValidator();
        $this->validator->initialize($this->context);
    }

    public function testBreakfastNotIncludedPassesRegardlessOfTags(): void
    {
        $product = $this->createProduct(breakfastIncluded: false, tagCodes: ['jidlo']);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($product, new BreakfastIncludedRequiresAccommodation());
    }

    public function testBreakfastIncludedWithUbytovaniTagPasses(): void
    {
        $product = $this->createProduct(breakfastIncluded: true, tagCodes: ['ubytovani']);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($product, new BreakfastIncludedRequiresAccommodation());
    }

    public function testBreakfastIncludedWithoutUbytovaniTagFails(): void
    {
        $product = $this->createProduct(breakfastIncluded: true, tagCodes: ['jidlo']);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())
            ->method('atPath')
            ->with('breakfastIncluded')
            ->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with('Snídani v ceně lze zapnout jen u ubytování.')
            ->willReturn($violationBuilder);

        $this->validator->validate($product, new BreakfastIncludedRequiresAccommodation());
    }

    /**
     * @param string[] $tagCodes
     */
    private function createProduct(bool $breakfastIncluded, array $tagCodes): Product
    {
        $product = new Product();
        $product->setBreakfastIncluded($breakfastIncluded);

        foreach ($tagCodes as $code) {
            $tag = new ProductTag();
            $tag->setCode($code);
            $tag->setName($code);
            $product->addTag($tag);
        }

        return $product;
    }
}
