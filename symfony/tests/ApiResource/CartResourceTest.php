<?php

declare(strict_types=1);

namespace App\Tests\ApiResource;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\ApiResource\CartResource;
use PHPUnit\Framework\TestCase;

/**
 * Verifies API Platform operation configuration on CartResource.
 */
class CartResourceTest extends TestCase
{
    private array $operations;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(CartResource::class);
        $apiResourceAttrs = $reflection->getAttributes(\ApiPlatform\Metadata\ApiResource::class);
        $this->assertNotEmpty($apiResourceAttrs, 'CartResource must have #[ApiResource] attribute');

        $apiResource = $apiResourceAttrs[0]->newInstance();
        $this->operations = [];
        foreach ($apiResource->getOperations() as $operation) {
            $this->operations[$operation->getUriTemplate()] = $operation;
        }
    }

    public function testDeleteOperationHasReadFalse(): void
    {
        $this->assertArrayHasKey('/cart/items/{itemId}', $this->operations, 'DELETE /cart/items/{itemId} route must exist');

        $deleteOp = $this->operations['/cart/items/{itemId}'];
        $this->assertInstanceOf(Delete::class, $deleteOp);
        $this->assertFalse(
            $deleteOp->canRead(),
            'DELETE /cart/items/{itemId} must have read: false to skip API Platform ReadProvider (which would try to find a CartResource entity by itemId and return 404)',
        );
    }

    public function testGetCartRouteExists(): void
    {
        $this->assertArrayHasKey('/cart', $this->operations);
        $this->assertInstanceOf(Get::class, $this->operations['/cart']);
    }

    public function testPostCartItemsRouteExists(): void
    {
        $this->assertArrayHasKey('/cart/items', $this->operations);
        $this->assertInstanceOf(Post::class, $this->operations['/cart/items']);
    }

    public function testPostCheckoutRouteExists(): void
    {
        $this->assertArrayHasKey('/cart/checkout', $this->operations);
        $this->assertInstanceOf(Post::class, $this->operations['/cart/checkout']);
    }
}
