<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\User;
use App\Service\JwtService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Tests for the Symfony Product API endpoint.
 *
 * These are kernel-level tests that verify the API layer logic
 * without making real HTTP requests.
 */
class ProductApiTest extends AbstractDatabaseKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    /**
     * Every Product operation requires ROLE_ADMIN, and the /symfony/api firewall
     * requires an authenticated user at all, so an anonymous request is answered
     * 401 and a plain participant 403 — neither ever reaches the resource.
     *
     * The admin is created here rather than looked up: User::getRoles() derives
     * ROLE_ADMIN from the organizator/admin/infopult/cfo role codes, and the test
     * fixtures grant none of them to anybody.
     *
     * @return array<string, string> server parameters for Request::create()
     */
    private function authenticatedRequestHeaders(): array
    {
        $container = static::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $adminRoleId = $connection->fetchOne(
            "SELECT id_role FROM role_seznam
             WHERE LOWER(kod_role) IN ('organizator', 'admin', 'infopult', 'cfo')
             ORDER BY id_role LIMIT 1",
        );
        if ($adminRoleId === false) {
            $this->markTestSkipped('No admin-granting role in role_seznam');
        }

        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => 'api_test_admin_' . uniqid(),
            UserEntityStructure::email => 'api.test.admin.' . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'API Test Admin',
        ])->_save()->_real();
        $userId = $user->getId();

        $connection->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role, posazen) VALUES (?, ?, NOW())',
            [$userId, $adminRoleId],
        );

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->find($userId);

        /** @var JwtService $jwtService */
        $jwtService = $container->get(JwtService::class);

        return [
            'HTTP_ACCEPT'        => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ];
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('API test product ' . uniqid());
        $product->setCode('API-TEST-' . strtoupper(uniqid()));
        $product->setCurrentPrice('1.00');
        $product->setState(1);
        $product->setDescription('');

        return $product;
    }

    /**
     * 401 and 403 are distinct outcomes and both are worth pinning; a test that
     * only exercised the happy path would stay green if either broke.
     *
     * Both are decided by the operation's own is_granted('ROLE_ADMIN'), not by
     * the access_control line: relaxing that line to PUBLIC_ACCESS leaves both
     * responses unchanged, while relaxing the operation to ROLE_USER turns the
     * 403 into a 200. So it is the resource guard these two pin down.
     */
    public function testAnonymousRequestIsRejected(): void
    {
        $kernel = static::getContainer()->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
        );

        $this->assertSame(401, $kernel->handle($request)->getStatusCode());
    }

    public function testNonAdminIsForbidden(): void
    {
        $container = static::getContainer();

        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => 'api_test_plain_' . uniqid(),
            UserEntityStructure::email => 'api.test.plain.' . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'API Test Participant',
        ])->_save()->_real();

        /** @var JwtService $jwtService */
        $jwtService = $container->get(JwtService::class);
        $token = $jwtService->generateJwtToken($jwtService->extractUserData($user));

        $kernel = $container->get('kernel');
        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT'        => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
        );

        $this->assertSame(403, $kernel->handle($request)->getStatusCode());
    }

    public function testProductApiReturnsJsonContentType(): void
    {
        $container = static::getContainer();

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products',
            'GET',
            [],
            [],
            [],
            $this->authenticatedRequestHeaders(),
        );

        $response = $kernel->handle($request);

        // API should return JSON, not HTML error pages
        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('json', $contentType, 'API must return JSON content type, got: ' . $contentType);
        $this->assertStringNotContainsString('text/html', $contentType, 'API must not return HTML');
    }

    public function testProductApiFilterByTag(): void
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // Both products are created here: filtering can only be proven by a
        // collection that has something to leave out, and the fixtures carry
        // no product_product_tag rows at all.
        $tagCode = 'api-test-filter-' . uniqid();
        // Inserted as SQL, not through the entity: product_tag.created_at is
        // NOT NULL without a default and ProductTag does not map it, so a tag
        // persisted through Doctrine is rejected by the database.
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $connection->executeStatement(
            'INSERT INTO product_tag (code, name, created_at) VALUES (?, ?, NOW())',
            [$tagCode, 'API filter test tag'],
        );
        $tag = $em->getRepository(ProductTag::class)->findOneBy([
            'code' => $tagCode,
        ]);

        $tagged = $this->createProduct();
        $tagged->addTag($tag);
        $em->persist($tagged);

        $untagged = $this->createProduct();
        $em->persist($untagged);
        $em->flush();

        $taggedId = $tagged->getId();
        $untaggedId = $untagged->getId();

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products?tags.code=' . $tagCode,
            'GET',
            [],
            [],
            [],
            $this->authenticatedRequestHeaders(),
        );

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode(), 'Response: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        // API Platform 3 renames hydra:member to member in the JSON-LD output.
        $this->assertArrayHasKey(
            'member',
            $data,
            'Collection response must carry members, got: ' . implode(', ', array_keys($data)),
        );

        $returnedIds = array_column($data['member'], 'id');
        $this->assertContains($taggedId, $returnedIds, 'Filtered collection must contain the product carrying the tag');
        $this->assertNotContains($untaggedId, $returnedIds, 'Filtered collection must exclude a product without the tag');

        foreach ($data['member'] as $product) {
            $tagCodes = array_column($product['tags'] ?? [], 'code');
            $this->assertContains($tagCode, $tagCodes, 'Product ' . ($product['name'] ?? '?') . ' should carry the filtered tag');
        }
    }

    public function testProductApiReturnsTagsWithCodeAndName(): void
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // Create the tagged product rather than depending on one existing: the
        // fixtures carry no product_product_tag rows, so a lookup only ever skips.
        $tag = $em->getRepository(ProductTag::class)->findOneBy([]);
        if ($tag === null) {
            $tag = new ProductTag();
            $tag->setCode('api-test-tag');
            $tag->setName('API test tag');
            $em->persist($tag);
        }

        $product = $this->createProduct();
        $product->addTag($tag);
        $em->persist($product);
        $em->flush();

        $productId = $product->getId();

        /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
        $kernel = $container->get('kernel');

        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/symfony/api/products/' . $productId,
            'GET',
            [],
            [],
            [],
            $this->authenticatedRequestHeaders(),
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
            $this->authenticatedRequestHeaders(),
        );

        $response = $kernel->handle($request);

        $this->assertSame(404, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('json', $contentType, 'Error responses must be JSON, got: ' . $contentType);
    }
}
