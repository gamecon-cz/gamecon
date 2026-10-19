<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Product;
use App\Entity\ProductTag;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Service\JwtService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Who may order on a participant's behalf. The rule sits on the operations, so it is tested
 * through HTTP: a provider called directly would never meet it.
 */
class DeskApiTest extends AbstractDatabaseKernelTestCase
{
    private const REFUSAL = 'Na objednávání za účastníky nemáš právo.';

    private mixed $puvodniNastaveni = null;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>|null}>
     */
    public static function deskOperationProvider(): iterable
    {
        yield 'read accommodation' => ['GET', '/symfony/api/admin/customer-accommodation?customerId=%d', null];
        yield 'order accommodation' => ['POST', '/symfony/api/admin/customer-accommodation', [
            'variantIds' => [],
        ]];
        yield 'read meals' => ['GET', '/symfony/api/admin/customer-meals?customerId=%d', null];
        yield 'order meals' => ['POST', '/symfony/api/admin/customer-meals', [
            'variantIds' => [],
        ]];
    }

    /**
     * The customer does not exist, so a check that ran after the lookup would answer 400 and
     * tell the caller which ids are real.
     *
     * @dataProvider deskOperationProvider
     *
     * @param array<string, mixed>|null $body
     */
    public function testOperatorWithoutADeskRightIsRefusedBeforeAnyLookup(string $method, string $uri, ?array $body): void
    {
        $nobody = $this->user('desk_nobody_');

        $response = $this->request($nobody, $method, $uri, $body, customerId: 999999999);

        self::assertSame(403, $response->getStatusCode(), $response->getContent(false));
        self::assertSame(self::REFUSAL, $response->toArray(false)['detail'] ?? null);
    }

    /**
     * Either right is enough: the desk is staffed from both admin screens.
     *
     * @return iterable<string, array{PermissionEnum}>
     */
    public static function deskPermissionProvider(): iterable
    {
        yield 'accommodation admin' => [PermissionEnum::ADMINISTRACE_UBYTOVANI];
        yield 'infopult' => [PermissionEnum::ADMINISTRACE_INFOPULT];
    }

    /**
     * @dataProvider deskPermissionProvider
     */
    public function testEitherDeskRightLetsTheOperatorIn(PermissionEnum $permission): void
    {
        $operator = $this->operator($permission);

        $response = $this->request($operator, 'GET', '/symfony/api/admin/customer-meals?customerId=%d', null, $this->user('desk_customer_')->getId());

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
    }

    /**
     * The catalogue is shared with participants; the desk only differs in ordering past the
     * deadline, and only a real operator gets that, whatever ?customerId says.
     */
    public function testOnlyTheDeskSeesMealsUnlockedPastTheDeadline(): void
    {
        $variantId = $this->meal();
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict('2099-01-01 00:00:00'),
        );
        $operator = $this->operator(PermissionEnum::ADMINISTRACE_INFOPULT);
        $nobody = $this->user('desk_nobody_');
        $uri = '/symfony/api/cart/meals?customerId=%d';

        $forOperator = $this->request($operator, 'GET', $uri, null, (int) $nobody->getId());
        $forNobody = $this->request($nobody, 'GET', $uri, null, (int) $operator->getId());

        self::assertFalse($this->lockedOf($forOperator->toArray(false), $variantId), $forOperator->getContent(false));
        self::assertTrue($this->lockedOf($forNobody->toArray(false), $variantId), $forNobody->getContent(false));
    }

    /**
     * @param array<string, mixed> $collection
     */
    private function lockedOf(array $collection, int $variantId): bool
    {
        foreach ($collection['hydra:member'] ?? $collection['member'] ?? [] as $meal) {
            if (($meal['variantId'] ?? null) === $variantId) {
                return $meal['locked'];
            }
        }
        self::fail(sprintf('Meal variant %d is not in the catalogue', $variantId));
    }

    private function user(string $loginPrefix): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => $loginPrefix . uniqid(),
            UserEntityStructure::email => $loginPrefix . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $user;
    }

    private function operator(PermissionEnum $permission): User
    {
        $operator = $this->user('desk_operator_');
        $roleId = $this->connection()->fetchOne(
            'SELECT prava_role.id_role
             FROM prava_role
             INNER JOIN role_seznam ON role_seznam.id_role = prava_role.id_role
             WHERE prava_role.id_prava = :pravo AND role_seznam.rocnik_role IN (:rok, -1)
             LIMIT 1',
            [
                'pravo' => $permission->value,
                'rok'   => ROCNIK,
            ],
        );
        self::assertNotFalse($roleId, sprintf('Some role of this year must grant %s', $permission->name));

        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
            [
                'uzivatel' => $operator->getId(),
                'role'     => $roleId,
            ],
        );

        return $operator;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(User $caller, string $method, string $uri, ?array $body, ?int $customerId): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->clientFor($caller)->request($method, sprintf($uri, $customerId), $body === null
            ? []
            : [
                'json' => $body + [
                    'customerId' => $customerId,
                ],
            ]);
    }

    private function clientFor(User $user): Client
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ]);
    }

    private function meal(): int
    {
        $this->connection()->executeStatement(
            'INSERT IGNORE INTO product_tag (code, name, created_at) VALUES (:code, :name, NOW())',
            [
                'code' => ProductTagCode::JIDLO->value,
                'name' => 'Jídlo',
            ],
        );
        $tag = $this->entityManager()->getRepository(ProductTag::class)->findOneBy([
            'code' => ProductTagCode::JIDLO->value,
        ]);
        self::assertNotNull($tag);

        $code = 'DESK-TEST-' . uniqid();
        $meal = new Product();
        $meal->setName('Oběd neděle');
        $meal->setCode($code);
        $meal->setCurrentPrice('140.00');
        $meal->setDescription('');
        $meal->setState(ProductStateEnum::PUBLIC);
        $meal->setAccommodationDay(4);
        $meal->addTag($tag);
        $this->entityManager()->persist($meal);

        $variant = new ProductVariant();
        $variant->setProduct($meal);
        $variant->setName('porce');
        $variant->setCode($code . '-V');
        $variant->setPrice('140.00');
        $variant->setPosition(0);
        $meal->addVariant($variant);
        $this->entityManager()->persist($variant);
        $this->entityManager()->flush();

        return (int) $variant->getId();
    }
}
