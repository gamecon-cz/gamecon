<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\JwtService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Tests\Factory\UserFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
    /**
     * @param array<string, string> $headers
     */
    private function adminClient(array $headers = []): Client
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
            $headers,
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

    /**
     * @param array<string, string> $headers
     */
    private function clientForUser(User $user, array $headers = []): Client
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ] + $headers);
    }

    /**
     * The serializer applies keys in the order sent, so a client may list variants first.
     */
    public function testNewVariantListedBeforeTheProductStateIsOfferedLikeTheProduct(): void
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::PREDMET->value,
                'name' => 'Předmět',
            ],
        );
        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => ProductTagCode::PREDMET->value,
        ]);
        $code = 'API-POREDI-' . strtoupper(uniqid());

        $response = $this->adminClient([
            'Content-Type' => 'application/ld+json',
        ])->request('POST', '/symfony/api/products', [
            'body' => json_encode([
                'name'         => 'Pořadí klíčů',
                'code'         => $code,
                'currentPrice' => '10.00',
                'variants'     => [[
                    'code'                  => $code . '-M',
                    'price'                 => null,
                    'capacity'              => null,
                    'reservedForOrganizers' => null,
                    'accommodationDay'      => null,
                    'position'              => 0,
                ]],
                'state'       => ProductStateEnum::SUSPENDED->value,
                'description' => '',
                'tags'        => ['/symfony/api/product_tags/' . $tag->getId()],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(201, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(
            ProductStateEnum::SUSPENDED->value,
            (int) $this->connection()->fetchOne('SELECT state FROM product_variant WHERE code = :code', [
                'code' => $code . '-M',
            ]),
        );
    }

    /**
     * The editor sends back each variant as it loaded it; the state may have changed since.
     */
    public function testEditorCannotOverwriteAVariantsState(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->connection()->executeStatement('UPDATE product_variant SET state = :stav WHERE id = :id', [
            'stav' => ProductStateEnum::SUSPENDED->value,
            'id'   => $variant->getId(),
        ]);

        $response = $this->ulozVarianty($product, [
            [
                ...$this->variantaJakoZEditoru($variant, capacity: 5),
                'state' => ProductStateEnum::PUBLIC->value,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(ProductStateEnum::SUSPENDED->value, (int) $this->connection()->fetchOne(
            'SELECT state FROM product_variant WHERE id = :id',
            [
                'id' => $variant->getId(),
            ],
        ));
    }

    /**
     * Only a product's single variant may go without a name; among several, a nameless one is
     * a blank option in the size picker.
     */
    public function testEditorRejectsAnUnnamedVariantAmongSeveral(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->ulozVarianty($product, [
            $this->variantaJakoZEditoru($variant, capacity: 5),
            $this->novaVarianta($product, name: null),
        ]);

        $this->assertVariantyMusiBytPojmenovane($response);
    }

    public function testEditorAddsANamedVariant(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->ulozVarianty($product, [
            $this->variantaJakoZEditoru($variant, capacity: 5),
            $this->novaVarianta($product, name: 'XL'),
        ]);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
    }

    /**
     * Case and surrounding spaces do not make a name different.
     */
    public function testEditorRejectsTwoVariantsWithTheSameName(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->ulozVarianty($product, [
            $this->variantaJakoZEditoru($variant, capacity: 5),
            $this->novaVarianta($product, name: 'm '),
        ]);

        $this->assertNazevVariantySeOpakuje($response, 'm ');
    }

    /**
     * Uniqueness is the validator's, not a database index's: the rows are updated one by one,
     * so an index would refuse the swap halfway through.
     */
    public function testEditorSwapsTwoVariantNames(): void
    {
        [$product, $variantM] = $this->produktSVariantou();
        $variantLId = json_decode($this->pridejVariantu($product, name: 'L')->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $variantL = $this->entityManager()->getRepository(ProductVariant::class)->find($variantLId);

        $response = $this->ulozVarianty($product, [
            [
                ...$this->variantaJakoZEditoru($variantM, capacity: 5),
                'name' => 'L',
            ],
            [
                ...$this->variantaJakoZEditoru($variantL, capacity: 5),
                'name'     => 'M',
                'position' => 1,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame('L', $this->connection()->fetchOne('SELECT name FROM product_variant WHERE id = :id', [
            'id' => $variantM->getId(),
        ]));
        self::assertSame('M', $this->connection()->fetchOne('SELECT name FROM product_variant WHERE id = :id', [
            'id' => $variantLId,
        ]));
    }

    public function testVariantCannotTakeASiblingsName(): void
    {
        [$product] = $this->produktSVariantou();

        $this->assertNazevVariantySeOpakuje($this->pridejVariantu($product, name: 'M'), 'M');
    }

    public function testUnnamedVariantCannotBeAddedBesideANamedOne(): void
    {
        [$product] = $this->produktSVariantou();

        $this->assertVariantyMusiBytPojmenovane($this->pridejVariantu($product, name: null));
    }

    public function testNamedVariantCannotBeAddedBesideAnUnnamedDefault(): void
    {
        [$product] = $this->produktSVariantou(name: null);

        $this->assertVariantyMusiBytPojmenovane($this->pridejVariantu($product, name: 'XL'));
    }

    public function testNamedVariantIsAddedBesideANamedOne(): void
    {
        [$product] = $this->produktSVariantou();

        $response = $this->pridejVariantu($product, name: 'XL');

        self::assertSame(201, $response->getStatusCode(), $response->getContent(false));
    }

    public function testVariantAmongSeveralCannotLoseItsName(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        self::assertSame(201, $this->pridejVariantu($product, name: 'XL')->getStatusCode());

        $response = $this->adminClient([
            'Content-Type' => 'application/merge-patch+json',
        ])->request('PATCH', '/symfony/api/product_variants/' . $variant->getId(), [
            'body' => json_encode([
                'name' => null,
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertVariantyMusiBytPojmenovane($response);
    }

    public function testEditorRejectsANegativeVariantCapacity(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->ulozVarianty($product, [$this->variantaJakoZEditoru($variant, capacity: -3)]);

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(5, (int) $this->connection()->fetchOne('SELECT capacity FROM product_variant WHERE id = :id', [
            'id' => $variant->getId(),
        ]));
    }

    public function testEditorRefusesToRemoveASoldVariant(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :product, :variant, :year, 1, NOW())',
            [
                'customer' => $this->createUser('api_test_buyer_')->getId(),
                'product'  => $product->getId(),
                'variant'  => $variant->getId(),
                'year'     => ROCNIK - 1,
            ],
        );

        $response = $this->ulozVarianty($product, []);

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame('variants', $response->toArray(false)['violations'][0]['propertyPath']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM product_variant WHERE id = :id', [
            'id' => $variant->getId(),
        ]));
    }

    /**
     * A PUT replaces the product wholesale, so orphan removal would drop every variant it omits.
     */
    public function testProductCannotBeReplacedWholesale(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->adminClient([
            'Content-Type' => 'application/ld+json',
        ])->request('PUT', '/symfony/api/products/' . $product->getId(), [
            'body' => json_encode([
                'name'         => $product->getName(),
                'code'         => $product->getCode(),
                'currentPrice' => $product->getCurrentPrice(),
                'state'        => $product->getState(),
                'tags'         => ['/symfony/api/product_tags/' . $product->getTags()->first()->getId()],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM product_variant WHERE id = :id', [
            'id' => $variant->getId(),
        ]), 'The variant must survive');
        self::assertSame(405, $response->getStatusCode(), $response->getContent(false));
    }

    public function testEditorRemovesAnUnsoldVariant(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->ulozVarianty($product, []);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM product_variant WHERE id = :id', [
            'id' => $variant->getId(),
        ]));
    }

    public function testSoldProductIsNotDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->prodej($product, $variant);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $product->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertStringContainsString('nejde smazat', $response->toArray(false)['detail']);
        self::assertSame(1, $this->pocetRadku('shop_predmety', 'id_predmetu', $product->getId()));
        self::assertSame(1, $this->pocetRadku('product_variant', 'id', $variant->getId()));
    }

    public function testProductWhoseVariantWasSoldUnderAnotherCatalogRowIsNotDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        [$jinyRadekKatalogu] = $this->produktSVariantou();
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :catalogRow, :variant, :year, 1, NOW())',
            [
                'customer'   => $this->createUser('api_test_buyer_')->getId(),
                'catalogRow' => $jinyRadekKatalogu->getId(),
                'variant'    => $variant->getId(),
                'year'       => ROCNIK - 1,
            ],
        );

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $product->getId());

        self::assertSame(1, $this->pocetRadku('shop_nakupy', 'variant_id', $variant->getId()), 'The purchase must keep its variant');
        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
    }

    public function testProductWithOnlyACancelledPurchaseIsNotDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->zrusenyProdej($product, $variant);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $product->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(1, $this->pocetRadku('shop_predmety', 'id_predmetu', $product->getId()));
    }

    public function testUnsoldProductIsDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $product->getId());

        self::assertSame(204, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(0, $this->pocetRadku('shop_predmety', 'id_predmetu', $product->getId()));
        self::assertSame(0, $this->pocetRadku('product_variant', 'id', $variant->getId()));
    }

    public function testSoldVariantIsNotDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->prodej($product, $variant);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/product_variants/' . $variant->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(1, $this->pocetRadku('product_variant', 'id', $variant->getId()));
        self::assertSame(1, $this->pocetRadku('shop_nakupy', 'variant_id', $variant->getId()));
    }

    public function testUnsoldVariantIsDeleted(): void
    {
        [, $variant] = $this->produktSVariantou();

        $response = $this->adminClient()->request('DELETE', '/symfony/api/product_variants/' . $variant->getId());

        self::assertSame(204, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(0, $this->pocetRadku('product_variant', 'id', $variant->getId()));
    }

    public function testUnsoldVariantIsDeletedBesideASoldOne(): void
    {
        [$product, $soldVariant] = $this->produktSVariantou();
        $this->prodej($product, $soldVariant);
        $unsoldIri = json_decode($this->pridejVariantu($product, name: 'XL')->getContent(), true, flags: JSON_THROW_ON_ERROR)['@id'];

        $response = $this->adminClient()->request('DELETE', $unsoldIri);

        self::assertSame(204, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(1, $this->pocetRadku('product_variant', 'product_id', $product->getId()));
    }

    public function testVariantWithOnlyACancelledPurchaseIsNotDeleted(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $this->zrusenyProdej($product, $variant);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/product_variants/' . $variant->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(1, $this->pocetRadku('product_variant', 'id', $variant->getId()));
    }

    /**
     * A size's own catalog row from the legacy layout has no variant of its own: the size is a
     * variant of its model, and its purchases point at the model.
     *
     * @return array{Product, ProductVariant}
     */
    private function zbylyRadekProdaneVelikosti(): array
    {
        [$model, $velikost] = $this->produktSVariantou();
        $radek = $this->createProduct()->setCode($velikost->getCode() . '-RADEK');
        $this->entityManager()->persist($radek);
        $this->entityManager()->flush();
        $this->connection()->executeStatement('UPDATE shop_predmety SET kod_predmetu = :code WHERE id_predmetu = :id', [
            'code' => $velikost->getCode(),
            'id'   => $radek->getId(),
        ]);

        return [$radek, $velikost, $model];
    }

    public function testLeftoverRowOfASoldSizeIsNotDeleted(): void
    {
        [$radek, $velikost, $model] = $this->zbylyRadekProdaneVelikosti();
        $this->prodej($model, $velikost);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $radek->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(1, $this->pocetRadku('shop_predmety', 'id_predmetu', $radek->getId()));
    }

    public function testLeftoverRowOfACancelledSizeIsNotDeleted(): void
    {
        [$radek, $velikost, $model] = $this->zbylyRadekProdaneVelikosti();
        $this->zrusenyProdej($model, $velikost);

        $response = $this->adminClient()->request('DELETE', '/symfony/api/products/' . $radek->getId());

        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
    }

    private function zrusenyProdej(Product $product, ProductVariant $variant): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy_zrusene (id_nakupu, id_uzivatele, id_predmetu, variant_id, rocnik, cena_nakupni, datum_nakupu)
             VALUES ((SELECT COALESCE(MAX(id_nakupu), 0) + 1 FROM shop_nakupy_zrusene AS existujici), :customer, :product, :variant, :year, 1, NOW())',
            [
                'customer' => $this->createUser('api_test_buyer_')->getId(),
                'product'  => $product->getId(),
                'variant'  => $variant->getId(),
                'year'     => ROCNIK - 1,
            ],
        );
    }

    private function prodej(Product $product, ProductVariant $variant): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
             VALUES (:customer, :product, :variant, :year, 1, NOW())',
            [
                'customer' => $this->createUser('api_test_buyer_')->getId(),
                'product'  => $product->getId(),
                'variant'  => $variant->getId(),
                'year'     => ROCNIK - 1,
            ],
        );
    }

    private function pocetRadku(string $tabulka, string $sloupec, ?int $hodnota): int
    {
        return (int) $this->connection()->fetchOne("SELECT COUNT(*) FROM {$tabulka} WHERE {$sloupec} = :hodnota", [
            'hodnota' => $hodnota,
        ]);
    }

    /**
     * @return array{Product, ProductVariant}
     */
    private function produktSVariantou(?string $name = 'M'): array
    {
        // product_tag.created_at is NOT NULL and unmapped, so the category row is created in SQL.
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::PREDMET->value,
                'name' => 'Předmět',
            ],
        );
        $product = $this->createProduct();
        $product->addTag($this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => ProductTagCode::PREDMET->value,
        ]));
        $variant = (new ProductVariant())->setName($name)->setCode($product->getCode() . '-M')->setCapacity(5)->setPosition(0);
        $variant->setProduct($product);
        $product->addVariant($variant);
        $this->entityManager()->persist($product);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return [$product, $variant];
    }

    /**
     * @return array<string, mixed>
     */
    private function variantaJakoZEditoru(ProductVariant $variant, int $capacity): array
    {
        return [
            '@id'                   => static::getContainer()->get('api_platform.iri_converter')->getIriFromResource($variant),
            'id'                    => $variant->getId(),
            'name'                  => $variant->getName(),
            'code'                  => $variant->getCode(),
            'price'                 => null,
            'capacity'              => $capacity,
            'reservedForOrganizers' => null,
            'accommodationDay'      => null,
            'position'              => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function novaVarianta(Product $product, ?string $name): array
    {
        return [
            'name'                  => $name,
            'code'                  => $product->getCode() . '-XL',
            'price'                 => null,
            'capacity'              => null,
            'reservedForOrganizers' => null,
            'accommodationDay'      => null,
            'position'              => 1,
        ];
    }

    private function pridejVariantu(Product $product, ?string $name): ResponseInterface
    {
        return $this->adminClient()->request('POST', '/symfony/api/product_variants', [
            'json' => [
                ...$this->novaVarianta($product, $name),
                'product' => '/symfony/api/products/' . $product->getId(),
            ],
        ]);
    }

    private function assertVariantyMusiBytPojmenovane(ResponseInterface $response): void
    {
        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $violations = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR)['violations'] ?? [];
        self::assertContains(
            'Produkt s více variantami potřebuje u každé varianty název (velikost, noc…).',
            array_column($violations, 'message'),
            $response->getContent(false),
        );
    }

    private function assertNazevVariantySeOpakuje(ResponseInterface $response, string $name): void
    {
        self::assertSame(422, $response->getStatusCode(), $response->getContent(false));
        $violations = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR)['violations'] ?? [];
        self::assertContains(
            "Název varianty „{$name}\" už má jiná varianta tohoto produktu.",
            array_column($violations, 'message'),
            $response->getContent(false),
        );
    }

    /**
     * @param list<array<string, mixed>> $varianty
     */
    private function ulozVarianty(Product $product, array $varianty): ResponseInterface
    {
        // Request-level headers replace the client's, token included, so the patch type is a default.
        $client = $this->adminClient([
            'Content-Type' => 'application/merge-patch+json',
        ]);

        return $client->request('PATCH', '/symfony/api/products/' . $product->getId(), [
            'body' => json_encode([
                'name'     => $product->getName(),
                'variants' => $varianty,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('API test product ' . uniqid());
        $product->setCode('API-TEST-' . strtoupper(uniqid()));
        $product->setCurrentPrice('1.00');
        $product->setState(ProductStateEnum::PUBLIC);
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

    /**
     * The admin editor shows each variant's capacity and what is left of it; the remaining
     * count is computed, not stored, so this is the only place it reaches the admin.
     */
    public function testProductDetailShowsVariantCapacityAndRemaining(): void
    {
        $product = $this->createProduct();
        $omezena = (new ProductVariant())->setName('M')->setCode($product->getCode() . '-M')->setCapacity(5)->setPosition(0);
        $neomezena = (new ProductVariant())->setName('L')->setCode($product->getCode() . '-L')->setCapacity(null)->setPosition(1);
        foreach ([$omezena, $neomezena] as $variant) {
            $variant->setProduct($product);
            $product->addVariant($variant);
            $this->entityManager()->persist($variant);
        }
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        $kupujici = $this->createUser('api_test_buyer_');
        for ($kus = 0; $kus < 2; ++$kus) {
            $this->connection()->executeStatement(
                'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, variant_id, rok, cena_nakupni, datum)
                 VALUES (:customer, :product, :variant, :year, 1, NOW())',
                [
                    'customer' => $kupujici->getId(),
                    'product'  => $product->getId(),
                    'variant'  => $omezena->getId(),
                    'year'     => ROCNIK,
                ],
            );
        }

        $data = $this->adminClient()->request('GET', '/symfony/api/products/' . $product->getId())->toArray();
        $variants = array_column($data['variants'], null, 'code');

        self::assertSame(5, $variants[$omezena->getCode()]['capacity']);
        self::assertSame(3, $variants[$omezena->getCode()]['remaining']);
        // API Platform leaves null properties out of the payload.
        self::assertArrayNotHasKey('capacity', $variants[$neomezena->getCode()]);
        self::assertNull($variants[$neomezena->getCode()]['remaining']);
        self::assertArrayNotHasKey('capacity', $data, 'One unlimited variant makes the product unlimited');
    }

    /**
     * The editor sends the product with its variants as full objects — the same shape it
     * read — so a changed capacity must be stored on the existing variant, not a new one.
     */
    public function testEditorSavesVariantCapacityOnTheExistingVariant(): void
    {
        [$product, $variant] = $this->produktSVariantou();
        $idVarianty = $variant->getId();

        $response = $this->ulozVarianty($product, [$this->variantaJakoZEditoru($variant, capacity: 8)]);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(
            [[
                'id'       => $idVarianty,
                'capacity' => 8,
            ]],
            array_map(
                static fn (array $radek): array => [
                    'id'       => (int) $radek['id'],
                    'capacity' => (int) $radek['capacity'],
                ],
                $this->connection()->fetchAllAssociative(
                    'SELECT id, capacity FROM product_variant WHERE product_id = :product',
                    [
                        'product' => $product->getId(),
                    ],
                ),
            ),
        );
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
