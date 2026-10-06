<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\TokenVersionService;
use App\Structure\Entity\UserEntityStructure;
use App\Tests\AbstractDatabaseKernelTestCase;
use Gamecon\Tests\Factory\UserFactory;

class TokenVersionServiceTest extends AbstractDatabaseKernelTestCase
{
    private function user(): int
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            UserEntityStructure::login => 'token_version_' . uniqid(),
            UserEntityStructure::email => 'token_version_' . uniqid() . '@example.invalid',
        ])->_save()->_real();

        return (int) $user->getId();
    }

    private function versions(): TokenVersionService
    {
        return static::getContainer()->get(TokenVersionService::class);
    }

    public function testUserWithoutARowIsOnVersionZero(): void
    {
        self::assertSame(0, $this->versions()->current($this->user()));
    }

    public function testEachInvalidationRaisesTheVersionByOne(): void
    {
        $userId = $this->user();

        $this->versions()->invalidate($userId);
        self::assertSame(1, $this->versions()->current($userId));

        $this->versions()->invalidate($userId);
        self::assertSame(2, $this->versions()->current($userId));
    }

    public function testInvalidatingOneUserLeavesOthersAlone(): void
    {
        $loggedOut = $this->user();
        $other = $this->user();

        $this->versions()->invalidate($loggedOut);

        self::assertSame(0, $this->versions()->current($other));
    }
}
