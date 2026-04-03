<?php

declare(strict_types=1);

namespace App\Tests\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\ApiResource\BulkCancelResource;
use App\ApiResource\CartResource;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that API operations have correct security attributes.
 *
 * Bare entities should be admin-only CRUD.
 * Public/user-facing endpoints use DTOs on ApiResource classes.
 */
class ApiSecurityTest extends TestCase
{
    /**
     * Product entity must be admin-only for all operations.
     *
     * @dataProvider productOperationClasses
     */
    public function testProductEntityOperationsRequireAdmin(string $operationClass): void
    {
        $operations = $this->getOperations(Product::class);

        $found = false;
        foreach ($operations as $operation) {
            if ($operation instanceof $operationClass) {
                $found = true;
                $this->assertStringContainsString(
                    'ROLE_ADMIN',
                    $operation->getSecurity() ?? '',
                    sprintf(
                        'Product %s operation must require ROLE_ADMIN, got: %s',
                        $operationClass,
                        $operation->getSecurity() ?? 'null',
                    ),
                );
            }
        }
        $this->assertTrue($found, "Product must have a {$operationClass} operation");
    }

    public static function productOperationClasses(): array
    {
        return [
            'GetCollection' => [GetCollection::class],
            'Get' => [Get::class],
            'Post' => [Post::class],
            'Put' => [Put::class],
            'Patch' => [Patch::class],
            'Delete' => [Delete::class],
        ];
    }

    /**
     * Cart operations must require ROLE_USER.
     *
     * @dataProvider cartOperationUris
     */
    public function testCartOperationsRequireUser(string $uriTemplate): void
    {
        $operations = $this->getOperations(CartResource::class);

        $found = false;
        foreach ($operations as $operation) {
            if ($operation->getUriTemplate() === $uriTemplate) {
                $found = true;
                $this->assertStringContainsString(
                    'ROLE_USER',
                    $operation->getSecurity() ?? '',
                    sprintf(
                        'Cart operation %s must require ROLE_USER, got: %s',
                        $uriTemplate,
                        $operation->getSecurity() ?? 'null',
                    ),
                );
            }
        }
        $this->assertTrue($found, "Cart must have operation for {$uriTemplate}");
    }

    public static function cartOperationUris(): array
    {
        return [
            'GET /cart' => ['/cart'],
            'GET /cart/meals' => ['/cart/meals'],
            'POST /cart/items' => ['/cart/items'],
            'DELETE /cart/items/{itemId}' => ['/cart/items/{itemId}'],
            'POST /cart/checkout' => ['/cart/checkout'],
        ];
    }

    /**
     * Bulk cancel must require ROLE_ADMIN.
     */
    public function testBulkCancelRequiresAdmin(): void
    {
        $operations = $this->getOperations(BulkCancelResource::class);

        foreach ($operations as $operation) {
            $this->assertStringContainsString(
                'ROLE_ADMIN',
                $operation->getSecurity() ?? '',
                sprintf(
                    'BulkCancel operation %s must require ROLE_ADMIN',
                    $operation->getUriTemplate(),
                ),
            );
        }
    }

    /**
     * No entity with #[ApiResource] should have PUBLIC_ACCESS on any operation.
     * Public data must be served via DTOs.
     */
    public function testNoEntityExposesPublicAccess(): void
    {
        $entityDir = dirname(__DIR__, 2) . '/src/Entity';
        $entityFiles = glob($entityDir . '/*.php');

        foreach ($entityFiles as $entityFile) {
            $className = 'App\\Entity\\' . basename($entityFile, '.php');
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $apiResourceAttrs = $reflection->getAttributes(ApiResource::class);
            if (empty($apiResourceAttrs)) {
                continue;
            }

            $apiResource = $apiResourceAttrs[0]->newInstance();
            $operations = $apiResource->getOperations() ?? [];

            foreach ($operations as $operation) {
                $security = $operation->getSecurity() ?? '';
                $this->assertStringNotContainsString(
                    'PUBLIC_ACCESS',
                    $security,
                    sprintf(
                        'Entity %s operation %s must not use PUBLIC_ACCESS — use DTOs for public endpoints',
                        $className,
                        $operation->getUriTemplate() ?? get_class($operation),
                    ),
                );
            }
        }
    }

    /**
     * @return iterable<\ApiPlatform\Metadata\Operation>
     */
    private function getOperations(string $className): iterable
    {
        $reflection = new \ReflectionClass($className);
        $apiResourceAttrs = $reflection->getAttributes(ApiResource::class);
        $this->assertNotEmpty($apiResourceAttrs, "{$className} must have #[ApiResource]");

        return $apiResourceAttrs[0]->newInstance()->getOperations();
    }
}
