<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests for the Symfony Product API endpoint.
 *
 * These are kernel-level tests that verify the API layer logic
 * without making real HTTP requests.
 */
class ProductApiTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testProductApiReturnsJsonContentType(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $response = $kernel->handle($request);

        // API should return JSON, not HTML error pages
        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('json', $contentType, 'API must return JSON content type, got: ' . $contentType);
        $this->assertStringNotContainsString('text/html', $contentType, 'API must not return HTML');
    }

    public function testProductApiFilterByTag(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // Check if jidlo tag exists
        $tag = $em->getRepository(ProductTag::class)->findOneBy(['code' => 'jidlo']);
        if ($tag === null) {
            $this->markTestSkipped('No jidlo tag in database');
        }

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products?tags.code=jidlo',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode(), 'Response: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('hydra:member', $data);

        // All returned products should have the jidlo tag
        foreach ($data['hydra:member'] as $product) {
            $tagCodes = array_column($product['tags'] ?? [], 'code');
            $this->assertContains('jidlo', $tagCodes, 'Product ' . ($product['name'] ?? '?') . ' should have jidlo tag');
        }
    }

    public function testProductApiReturnsTagsWithCodeAndName(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // Find any product with tags
        /** @var Connection $conn */
        $conn = $container->get(Connection::class);
        $productId = $conn->fetchOne('SELECT product_id FROM product_product_tag LIMIT 1');
        if ($productId === false) {
            $this->markTestSkipped('No products with tags in database');
        }

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products/' . $productId,
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['tags'], 'Product should have tags');

        $firstTag = $data['tags'][0];
        $this->assertArrayHasKey('code', $firstTag, 'Tag must have code field');
        $this->assertArrayHasKey('name', $firstTag, 'Tag must have name field');
    }

    public function testApiErrorReturnsJsonNotHtml(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        // Request a non-existent product — should return 404 in JSON format
        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products/999999',
            'GET',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $response = $kernel->handle($request);

        $this->assertSame(404, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('json', $contentType, 'Error responses must be JSON, got: ' . $contentType);
    }
}
