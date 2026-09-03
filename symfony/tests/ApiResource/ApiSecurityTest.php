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
use App\ApiResource\KfcResource;
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
            'Get'           => [Get::class],
            'Post'          => [Post::class],
            'Put'           => [Put::class],
            'Patch'         => [Patch::class],
            'Delete'        => [Delete::class],
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
            'GET /cart'                   => ['/cart'],
            'GET /cart/meals'             => ['/cart/meals'],
            'POST /cart/items'            => ['/cart/items'],
            'DELETE /cart/items/{itemId}' => ['/cart/items/{itemId}'],
            'POST /cart/checkout'         => ['/cart/checkout'],
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
     * All KFC operations must require ROLE_ADMIN.
     *
     * @dataProvider kfcOperationUris
     */
    public function testKfcOperationsRequireAdmin(string $uriTemplate): void
    {
        $operations = $this->getOperations(KfcResource::class);

        $found = false;
        foreach ($operations as $operation) {
            if ($operation->getUriTemplate() === $uriTemplate) {
                $found = true;
                $this->assertStringContainsString(
                    'ROLE_ADMIN',
                    $operation->getSecurity() ?? '',
                    sprintf(
                        'KFC operation %s must require ROLE_ADMIN, got: %s',
                        $uriTemplate,
                        $operation->getSecurity() ?? 'null',
                    ),
                );
            }
        }
        $this->assertTrue($found, "KFC must have operation for {$uriTemplate}");
    }

    public static function kfcOperationUris(): array
    {
        return [
            'GET /kfc/products' => ['/kfc/products'],
            'GET /kfc/grids'    => ['/kfc/grids'],
            'POST /kfc/grids'   => ['/kfc/grids'],
            'POST /kfc/sale'    => ['/kfc/sale'],
        ];
    }

    /**
     * An entity is never reachable through the public API — public data is served
     * by DTOs on ApiResource classes. So every operation on an entity must be
     * guarded, and an operation with no security at all is a bug, not a default:
     * API Platform leaves an unguarded operation open to whoever the firewall
     * lets through.
     *
     * Checking for the absence of PUBLIC_ACCESS alone would not catch that,
     * because the dangerous case declares nothing rather than declaring the
     * wrong thing.
     *
     * @dataProvider entityApiResourceOperations
     */
    public function testEveryEntityOperationIsGuarded(
        string $className,
        string $operationName,
        ?string $security,
    ): void {
        $this->assertNotNull(
            $security,
            sprintf(
                'Entity %s operation %s declares no security — entities must never be publicly reachable, use a DTO for public data',
                $className,
                $operationName,
            ),
        );
        $this->assertStringNotContainsString(
            'PUBLIC_ACCESS',
            $security,
            sprintf(
                'Entity %s operation %s must not use PUBLIC_ACCESS — use DTOs for public endpoints',
                $className,
                $operationName,
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function entityApiResourceOperations(): iterable
    {
        $entityDir = dirname(__DIR__, 2) . '/src/Entity';
        $entityFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityDir, \FilesystemIterator::SKIP_DOTS),
        );

        $found = false;
        foreach ($entityFiles as $entityFile) {
            if ($entityFile->getExtension() !== 'php') {
                continue;
            }
            $relativePath = substr($entityFile->getPathname(), strlen($entityDir) + 1);
            $className = 'App\\Entity\\' . str_replace('/', '\\', substr($relativePath, 0, -4));
            if (! class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $apiResourceAttrs = $reflection->getAttributes(ApiResource::class);
            if ($apiResourceAttrs === []) {
                continue;
            }

            $apiResource = $apiResourceAttrs[0]->newInstance();
            // An operation inherits the class-level security only once API Platform
            // has merged the metadata; the raw attribute reports null for it.
            $classSecurity = $apiResource->getSecurity();

            foreach ($apiResource->getOperations() ?? [] as $operation) {
                $found = true;
                $operationName = $operation->getUriTemplate() ?? $operation::class;
                yield $className . '::' . $operationName => [
                    $className,
                    $operationName,
                    $operation->getSecurity() ?? $classSecurity,
                ];
            }
        }

        self::assertTrue($found, 'No entity with #[ApiResource] found — the guard below would pass vacuously');
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
