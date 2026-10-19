<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Entity\OrderItemLog;
use App\Entity\User;
use App\Service\AuditOriginProvider;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditOriginProviderTest extends TestCase
{
    /**
     * @var list<string>|null
     */
    private ?array $originalArguments = null;

    protected function setUp(): void
    {
        $this->originalArguments = $_SERVER['argv'] ?? null;
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->originalArguments;
    }

    public function testRouteNameIsPreferredOverThePath(): void
    {
        $request = Request::create('/symfony/api/cart/items/17?token=secret');
        $request->attributes->set('_route', 'api_cart_items_post');

        self::assertSame('api_cart_items_post', $this->provider(null, $request)->getOrigin());
    }

    public function testPathWithoutQueryIsUsedWhenThereIsNoRoute(): void
    {
        $request = Request::create('/admin/finance/shop?token=secret');

        self::assertSame('/admin/finance/shop', $this->provider(null, $request)->getOrigin());
    }

    public function testOriginIsCutToTheColumnLength(): void
    {
        $request = Request::create('/' . str_repeat('a', 400));
        $columnLength = (new \ReflectionProperty(OrderItemLog::class, 'origin'))
            ->getAttributes(ORM\Column::class)[0]
            ->newInstance()
            ->length;

        self::assertSame($columnLength, mb_strlen($this->provider(null, $request)->getOrigin()));
    }

    public function testOriginIsCutByCharactersNotBytes(): void
    {
        $origin = $this->provider(null, Request::create('/' . str_repeat('č', 400)))->getOrigin();

        self::assertSame(255, mb_strlen($origin));
        self::assertTrue(mb_check_encoding($origin, 'UTF-8'), 'A cut inside a character would not be valid UTF-8');
    }

    public function testConsoleOriginNamesTheCommand(): void
    {
        $_SERVER['argv'] = ['/var/www/html/gamecon/bin/console', 'app:grant-switch-user-role-to-devs', '--force'];

        self::assertSame('console app:grant-switch-user-role-to-devs', $this->provider()->getOrigin());
    }

    public function testOtherScriptsLoseTheirArguments(): void
    {
        $_SERVER['argv'] = ['/var/www/html/gamecon/admin/cron.php', 'a-secret-key'];

        self::assertSame('cron.php', $this->provider()->getOrigin());
    }

    public function testSignedInUserIsTheActor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        self::assertSame(42, $this->provider($user)->getActorId());
    }

    public function testNobodyIsTheActorOnTheConsole(): void
    {
        self::assertNull($this->provider()->getActorId());
    }

    private function provider(?User $user = null, ?Request $request = null): AuditOriginProvider
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }

        return new AuditOriginProvider($security, $requestStack);
    }
}
