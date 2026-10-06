<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\Service\JwtService;
use App\Service\TokenVersionService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Firebase\JWT\JWT;
use Gamecon\Tests\Factory\UserFactory;

/**
 * What logging out does to the tokens the admin pages already handed out: every one stops
 * working at once, and a token issued afterwards works again.
 */
class TokenRevocationApiTest extends AbstractDatabaseKernelTestCase
{
    private const AUTHENTICATED_URI = '/symfony/api/cart/meals';

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testTokenStopsWorkingWhenTheUserIsLoggedOut(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);

        self::assertSame(200, $this->get($token)->getStatusCode());

        $this->versions()->invalidate((int) $user->getId());

        $refused = $this->get($token);
        self::assertSame(401, $refused->getStatusCode(), $refused->getContent(false));
        self::assertSame('Token has been revoked', $refused->toArray(false)['message'] ?? null);
    }

    /**
     * The real logout is `Uzivatel::odhlas()`. Session functions warn once the test runner has
     * written output, which says nothing about what is being tested.
     */
    public function testLegacyLogoutRevokesTheUsersTokens(): void
    {
        $user = $this->user();
        $token = $this->tokenFor($user);
        self::assertSame(200, $this->get($token)->getStatusCode());

        set_error_handler(static fn (): bool => true);
        try {
            \Uzivatel::zId((int) $user->getId())->odhlas(false);
        } finally {
            restore_error_handler();
        }

        self::assertSame(401, $this->get($token)->getStatusCode());
    }

    public function testEveryTokenHandedOutBeforeTheLogoutStopsWorking(): void
    {
        $user = $this->user();
        $firstPage = $this->tokenFor($user);
        $secondTab = $this->tokenFor($user);

        $this->versions()->invalidate((int) $user->getId());

        self::assertSame(401, $this->get($firstPage)->getStatusCode());
        self::assertSame(401, $this->get($secondTab)->getStatusCode());
    }

    public function testTokenIssuedAfterTheLogoutWorks(): void
    {
        $user = $this->user();
        $this->versions()->invalidate((int) $user->getId());

        self::assertSame(200, $this->get($this->tokenFor($user))->getStatusCode());
    }

    public function testLoggingOneUserOutLeavesAnotherUsersTokenWorking(): void
    {
        $loggedOut = $this->user();
        $other = $this->user();
        $otherToken = $this->tokenFor($other);

        $this->versions()->invalidate((int) $loggedOut->getId());

        self::assertSame(200, $this->get($otherToken)->getStatusCode());
    }

    /**
     * A page rendered before versions existed still holds a token without one; it counts as
     * version 0, so it works until the user's first logout and not after.
     */
    public function testTokenWithoutAVersionWorksUntilTheFirstLogout(): void
    {
        $user = $this->user();
        $unversioned = JWT::encode([
            'iss'  => 'gamecon-php',
            'aud'  => 'gamecon-csharp',
            'iat'  => time(),
            'exp'  => time() + 3600,
            'user' => [
                'id'    => $user->getId(),
                'login' => $user->getLogin(),
            ],
        ], static::getContainer()->getParameter('app.secret'), 'HS256');

        self::assertSame(200, $this->get($unversioned)->getStatusCode());

        $this->versions()->invalidate((int) $user->getId());

        self::assertSame(401, $this->get($unversioned)->getStatusCode());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => 'revocation_' . uniqid(),
            UserEntityStructure::email => 'revocation_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return $user;
    }

    private function versions(): TokenVersionService
    {
        return static::getContainer()->get(TokenVersionService::class);
    }

    private function tokenFor(User $user): string
    {
        /** @var JwtService $jwtService */
        $jwtService = static::getContainer()->get(JwtService::class);

        return $jwtService->generateJwtToken($jwtService->extractUserData($user));
    }

    private function get(string $token): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->clientWith($token)->request('GET', self::AUTHENTICATED_URI);
    }

    private function clientWith(string $token): Client
    {
        return $this->jsonLdClient([
            'Authorization' => 'Bearer ' . $token,
        ]);
    }
}
