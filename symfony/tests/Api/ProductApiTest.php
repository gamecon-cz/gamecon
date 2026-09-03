<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
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
     */
    private function adminClient(): Client
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

        $userId = $this->createUser('api_test_admin_')->getId();

        $connection->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role, posazen) VALUES (?, ?, NOW())',
            [$userId, $adminRoleId],
        );

        // The role was granted with SQL, so the already-loaded User knows nothing
        // about it and getRoles() would still report a plain participant.
        $this->entityManager()->clear();

        return $this->clientForUser(
            $this->entityManager()->getRepository(User::class)->find($userId),
        );
    }

    private function createUser(string $loginPrefix): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => $loginPrefix . uniqid(),
            UserEntityStructure::email => $loginPrefix . uniqid() . '@example.invalid',
            UserEntityStructure::jmeno => 'API Test User',
        ])->_save()->_real();

        return $user;
    }

    private function clientForUser(User $user): Client
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ]);
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
        $response = $this->jsonLdClient()->request('GET', '/symfony/api/products');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testNonAdminIsForbidden(): void
    {
        $client = $this->clientForUser($this->createUser('api_test_plain_'));

        $this->assertSame(403, $client->request('GET', '/symfony/api/products')->getStatusCode());
    }

    public function testProductApiReturnsJsonContentType(): void
    {
        $response = $this->adminClient()->request('GET', '/symfony/api/products');

        // API should return JSON, not HTML error pages
        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
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

        $response = $this->adminClient()->request('GET', '/symfony/api/products?tags.code=' . $tagCode);

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        // API Platform 4 emits member, not hydra:member — hydra_prefix defaults
        // to false and the project overrides nothing.
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
            /** @var Connection $connection */
            $connection = $container->get(Connection::class);
            $connection->executeStatement(
                "INSERT INTO product_tag (code, name, created_at) VALUES ('api-test-tag', 'API test tag', NOW())",
            );
            $tag = $em->getRepository(ProductTag::class)->findOneBy([
                'code' => 'api-test-tag',
            ]);
        }

        $product = $this->createProduct();
        $product->addTag($tag);
        $em->persist($product);
        $em->flush();

        $response = $this->adminClient()->request('GET', '/symfony/api/products/' . $product->getId());

        $this->assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        $this->assertNotEmpty($data['tags'], 'Product should have tags');

        $firstTag = $data['tags'][0];
        $this->assertArrayHasKey('code', $firstTag, 'Tag must have code field');
        $this->assertArrayHasKey('name', $firstTag, 'Tag must have name field');
    }

    public function testApiErrorReturnsJsonNotHtml(): void
    {
        // Request a non-existent product — should return 404 in JSON format
        $response = $this->adminClient()->request('GET', '/symfony/api/products/999999');

        $this->assertSame(404, $response->getStatusCode());

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $this->assertStringContainsString('json', $contentType, 'Error responses must be JSON, got: ' . $contentType);
    }
}
