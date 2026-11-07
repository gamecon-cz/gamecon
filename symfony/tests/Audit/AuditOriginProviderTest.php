<?php

declare(strict_types=1);

namespace App\Tests\Audit;

use App\Entity\OrderItemLog;
use App\Entity\User;
use App\Service\AuditOriginProvider;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditOriginProviderTest extends TestCase
{
    /**
     * Neither this nor the paths outside of it exist, so no symlink on the machine changes the expectations.
     */
    private const string APP_ROOT = '/srv/audit-origin-test/app';

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

    /**
     * The name comes from the console's own event, so an abbreviation, an alias or an option given
     * before the command cannot change it, and nothing typed after it can reach the log.
     */
    public function testConsoleOriginNamesTheCommandThatTheApplicationResolved(): void
    {
        $provider = $this->provider();
        $provider->onConsoleCommand($this->commandEvent('cache:clear'));
        $_SERVER['argv'] = [self::APP_ROOT . '/bin/console', '--token=a-secret-token', 'cache:cl', '--force'];

        self::assertSame('console cache:clear', $provider->getOrigin());
    }

    public function testRunningCommandIsPreferredOverARequest(): void
    {
        $provider = $this->provider(null, Request::create('/admin/finance/shop'));
        $provider->onConsoleCommand($this->commandEvent('app:grant-switch-user-role-to-devs'));

        self::assertSame('console app:grant-switch-user-role-to-devs', $provider->getOrigin());
    }

    public function testConsoleWithoutACommandIsJustAScript(): void
    {
        $_SERVER['argv'] = [self::APP_ROOT . '/bin/console', 'a-secret-token'];

        self::assertSame('bin/console', $this->provider()->getOrigin());
    }

    public function testCommandRunInsideAnotherOneDoesNotHideTheOuterOneWhenItEnds(): void
    {
        $provider = $this->provider();
        $provider->onConsoleCommand($this->commandEvent('app:import'));
        $provider->onConsoleCommand($this->commandEvent('cache:clear'));
        $nestedOrigin = $provider->getOrigin();
        $provider->onConsoleTerminate();
        $_SERVER['argv'] = [self::APP_ROOT . '/bin/console'];

        self::assertSame(['console cache:clear', 'console app:import'], [$nestedOrigin, $provider->getOrigin()]);

        $provider->onConsoleTerminate();

        self::assertSame('bin/console', $provider->getOrigin());
    }

    public function testCommandIsForgottenWhenItTerminates(): void
    {
        $provider = $this->provider();
        $provider->onConsoleCommand($this->commandEvent('cache:clear'));
        $provider->onConsoleTerminate();
        $_SERVER['argv'] = [self::APP_ROOT . '/bin/console'];

        self::assertSame('bin/console', $provider->getOrigin());
    }

    public function testOtherScriptsLoseTheirArguments(): void
    {
        $_SERVER['argv'] = [self::APP_ROOT . '/admin/cron.php', 'a-secret-key'];

        self::assertSame('admin/cron.php', $this->provider()->getOrigin());
    }

    public function testScriptsOfTheSameNameInDifferentDirectoriesStayApart(): void
    {
        $_SERVER['argv'] = [self::APP_ROOT . '/admin/cron/foo.php'];
        $first = $this->provider()->getOrigin();
        $_SERVER['argv'] = [self::APP_ROOT . '/admin/cron/jobs/foo.php'];
        $second = $this->provider()->getOrigin();

        self::assertSame(['admin/cron/foo.php', 'admin/cron/jobs/foo.php'], [$first, $second]);
    }

    public function testRelativeScriptIsResolvedAgainstTheWorkingDirectory(): void
    {
        $_SERVER['argv'] = ['./foo.php'];
        $workingDirectory = getcwd();
        chdir(__DIR__);

        try {
            self::assertSame('Audit/foo.php', $this->provider(null, null, dirname(__DIR__))->getOrigin());
        } finally {
            chdir((string) $workingDirectory);
        }
    }

    public function testScriptOutsideTheAppKeepsItsAbsolutePath(): void
    {
        $_SERVER['argv'] = ['/opt/audit-origin-test/bin/composer', 'install'];

        self::assertSame('/opt/audit-origin-test/bin/composer', $this->provider()->getOrigin());
    }

    public function testSiblingDirectoryWithTheSamePrefixIsNotInsideTheApp(): void
    {
        $_SERVER['argv'] = [self::APP_ROOT . '-old/foo.php'];

        self::assertSame(self::APP_ROOT . '-old/foo.php', $this->provider()->getOrigin());
    }

    public function testParentReferencesAreCollapsed(): void
    {
        $_SERVER['argv'] = ['../Service/foo.php'];
        $workingDirectory = getcwd();
        chdir(__DIR__);

        try {
            self::assertSame('Service/foo.php', $this->provider(null, null, dirname(__DIR__))->getOrigin());
        } finally {
            chdir((string) $workingDirectory);
        }
    }

    public function testMissingArgumentsGiveCli(): void
    {
        $_SERVER['argv'] = [];

        self::assertSame('cli', $this->provider()->getOrigin());
    }

    /**
     * What `php -` and `php -r` put into argv[0]: they are not files, so they must not become paths.
     */
    #[DataProvider('providePseudoScripts')]
    public function testPseudoScriptsStayAsTheyAre(string $pseudoScript): void
    {
        $_SERVER['argv'] = [$pseudoScript];
        $workingDirectory = getcwd();
        chdir(__DIR__);

        try {
            self::assertSame($pseudoScript, $this->provider(null, null, dirname(__DIR__))->getOrigin());
        } finally {
            chdir((string) $workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePseudoScripts(): iterable
    {
        yield 'standard input' => ['-'];
        yield 'code on the command line' => ['Standard input code'];
    }

    public function testArgumentsOfAScriptNamedConsoleAreNotLogged(): void
    {
        $_SERVER['argv'] = ['/opt/audit-origin-test/bin/console', 'a-secret-token'];

        self::assertSame('/opt/audit-origin-test/bin/console', $this->provider()->getOrigin());
    }

    public function testScriptReachedThroughASymlinkedDirectoryIsInsideTheApp(): void
    {
        $filesystem = new Filesystem();
        $base = dirname(__DIR__, 2) . '/var/tmp/audit-origin-' . uniqid();

        try {
            $filesystem->mkdir($base . '/release/admin/cron', 0775);
            $filesystem->symlink($base . '/release', $base . '/current');
            $_SERVER['argv'] = [$base . '/current/admin/cron/foo.php'];

            self::assertSame('admin/cron/foo.php', $this->provider(null, null, $base . '/release')->getOrigin());
        } finally {
            $filesystem->remove($base);
        }
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

    private function commandEvent(string $commandName): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command($commandName), new ArrayInput([]), new NullOutput());
    }

    private function provider(?User $user = null, ?Request $request = null, string $appRootDir = self::APP_ROOT): AuditOriginProvider
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }

        return new AuditOriginProvider($security, $requestStack, $appRootDir);
    }
}
