<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ProductTag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

class ProductTagTest extends TestCase
{
    public function testCodeHasSerializationGroups(): void
    {
        $reflection = new \ReflectionProperty(ProductTag::class, 'code');
        $attributes = $reflection->getAttributes(Groups::class);

        $this->assertNotEmpty($attributes, 'ProductTag::code must have #[Groups] attribute');

        $groups = $attributes[0]->newInstance()->getGroups();
        $this->assertContains('product:read', $groups);
        $this->assertContains('product:list', $groups);
    }

    public function testNameHasSerializationGroups(): void
    {
        $reflection = new \ReflectionProperty(ProductTag::class, 'name');
        $attributes = $reflection->getAttributes(Groups::class);

        $this->assertNotEmpty($attributes, 'ProductTag::name must have #[Groups] attribute');

        $groups = $attributes[0]->newInstance()->getGroups();
        $this->assertContains('product:read', $groups);
        $this->assertContains('product:list', $groups);
    }
}
