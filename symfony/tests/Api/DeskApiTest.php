<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\Enum\PermissionEnum;
use App\Service\JwtService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

/**
 * Who may order on a participant's behalf. The rule sits on the operations, so it is tested
 * through HTTP: a provider called directly would never meet it.
 */
class DeskApiTest extends AbstractDatabaseKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
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
     * Refused before the customer is even looked up, so the answer says nothing about which
     * ids exist.
     *
     * @dataProvider deskOperationProvider
     *
     * @param array<string, mixed>|null $body
     */
    public function testOperatorWithoutADeskRightIsRefused(string $method, string $uri, ?array $body): void
    {
        $customerId = $this->user('desk_customer_')->getId();

        $response = $this->clientFor($this->user('desk_nobody_'))->request($method, sprintf($uri, $customerId), $body === null
            ? []
            : [
                'json' => $body + [
                    'customerId' => $customerId,
                ],
            ]);

        self::assertSame(403, $response->getStatusCode(), $response->getContent(false));
        self::assertSame('Na objednávání za účastníky nemáš právo.', $response->toArray(false)['detail'] ?? null);
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
        $customerId = $this->user('desk_customer_')->getId();
        $operator = $this->user('desk_operator_');
        $this->grant($operator, $permission);

        $response = $this->clientFor($operator)->request('GET', sprintf('/symfony/api/admin/customer-meals?customerId=%d', $customerId));

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
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

    private function grant(User $user, PermissionEnum $permission): void
    {
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
                'uzivatel' => $user->getId(),
                'role'     => $roleId,
            ],
        );
    }

    private function clientFor(User $user): Client
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $jwtService->generateJwtToken($jwtService->extractUserData($user)),
        ]);
    }
}
