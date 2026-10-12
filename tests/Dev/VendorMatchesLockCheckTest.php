<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the real script, because the contract is its exit code: CI fails on anything but 0.
 */
class VendorMatchesLockCheckTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gamecon-vendor-check-' . uniqid('', true);
        (new Filesystem())->mkdir($this->root . '/vendor/composer', 0775);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    /**
     * @test
     */
    public function vendorInSyncWithTheLockPasses(): void
    {
        $knihovna = $this->package('acme/lib', '1.2.0', 'aaa');
        $nastroj = $this->package('acme/dev-tool', '2.0.0', 'bbb');
        $this->writeProject([$knihovna], [$nastroj], [$knihovna, $nastroj]);

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('2 packages', $output);
    }

    /**
     * @test
     */
    public function olderVersionInVendorFailsAndNamesBothVersions(): void
    {
        $this->writeProject(
            [$this->package('guzzlehttp/guzzle', '7.15.5', 'new')],
            [],
            [$this->package('guzzlehttp/guzzle', '7.15.1', 'old')],
        );

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('guzzlehttp/guzzle', $output);
        self::assertStringContainsString('7.15.5', $output);
        self::assertStringContainsString('7.15.1', $output);
    }

    /**
     * @test
     */
    public function developmentPackageOlderInVendorFails(): void
    {
        $this->writeProject(
            [],
            [$this->package('acme/dev-tool', '2.1.0', 'new')],
            [$this->package('acme/dev-tool', '2.0.0', 'old')],
        );

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/dev-tool', $output);
    }

    /**
     * @test
     */
    public function packageMissingFromVendorFails(): void
    {
        $this->writeProject([$this->package('acme/lib', '1.0.0', 'aaa'), $this->package('acme/new', '1.0.0', 'bbb')], [], [
            $this->package('acme/lib', '1.0.0', 'aaa'),
        ]);

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/new', $output);
    }

    /**
     * @test
     */
    public function packageInVendorThatTheLockDoesNotKnowFails(): void
    {
        $this->writeProject([$this->package('acme/lib', '1.0.0', 'aaa')], [], [
            $this->package('acme/lib', '1.0.0', 'aaa'),
            $this->package('acme/removed', '1.0.0', 'bbb'),
        ]);

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/removed', $output);
    }

    /**
     * @test
     */
    public function sameVersionFromAnotherCommitFails(): void
    {
        $this->writeProject(
            [$this->package('acme/branch', 'dev-main', 'new')],
            [],
            [$this->package('acme/branch', 'dev-main', 'old')],
        );

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/branch', $output);
    }

    /**
     * A metapackage installs no files, but installed.json and installed.php are tracked and carry
     * its commit, so the next composer install rewrites them.
     *
     * @test
     */
    public function metapackageFromAnotherCommitFails(): void
    {
        $this->writeProject(
            [],
            [$this->package('roave/security-advisories', 'dev-latest', 'new', 'metapackage')],
            [$this->package('roave/security-advisories', 'dev-latest', 'old', 'metapackage')],
        );

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('roave/security-advisories', $output);
    }

    /**
     * @test
     */
    public function commitsThatShareAPrefixAreDifferent(): void
    {
        $this->writeProject(
            [$this->package('acme/lib', '1.0.0', '63038786ae51b572f8f1de5a29079bea5971dd84')],
            [],
            [$this->package('acme/lib', '1.0.0', '63038786ae51ffffffffffffffffffffffffffff')],
        );

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/lib', $output);
    }

    /**
     * @test
     */
    public function packageMovedBetweenRequireAndRequireDevFails(): void
    {
        $nastroj = $this->package('acme/tool', '1.0.0', 'aaa');
        $this->writeProject([], [$nastroj], [$nastroj], devPackageNames: []);

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/tool', $output);
    }

    /**
     * A bump that adds a package whose directory was never committed leaves installed.json
     * claiming it is there.
     *
     * @test
     */
    public function packageDirectoryMissingFromVendorFails(): void
    {
        $knihovna = $this->package('acme/lib', '1.0.0', 'aaa');
        $this->writeProject([$knihovna], [], [$knihovna], createDirectories: false);

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('acme/lib', $output);
    }

    /**
     * Without installed.json nothing was compared, and a pass would say vendor/ is current.
     * The lock is empty so that comparing against an empty installed.json would pass.
     *
     * @test
     */
    public function missingInstalledJsonFails(): void
    {
        file_put_contents($this->root . '/composer.lock', json_encode([
            'packages'     => [],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR));

        [$exitCode, $output] = $this->runCheck();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('installed.json is missing', $output);
    }

    /**
     * @return array<string, mixed> a package as composer.lock lists it
     */
    private function package(string $name, string $version, string $reference, string $type = 'library'): array
    {
        return [
            'name'    => $name,
            'version' => $version,
            'type'    => $type,
            'source'  => [
                'type'      => 'git',
                'url'       => 'https://example.invalid/' . $name . '.git',
                'reference' => $reference,
            ],
            'dist' => [
                'type'      => 'zip',
                'url'       => 'https://example.invalid/' . $name . '.zip',
                'reference' => $reference,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $lockPackages
     * @param list<array<string, mixed>> $lockDevPackages
     * @param list<array<string, mixed>> $vendorPackages
     * @param list<string>|null          $devPackageNames what installed.json calls dev; null = what the lock calls dev
     */
    private function writeProject(
        array $lockPackages,
        array $lockDevPackages,
        array $vendorPackages,
        bool $createDirectories = true,
        ?array $devPackageNames = null,
    ): void {
        file_put_contents($this->root . '/composer.lock', json_encode([
            'packages'     => $lockPackages,
            'packages-dev' => $lockDevPackages,
        ], JSON_THROW_ON_ERROR));

        $installed = [];
        foreach ($vendorPackages as $package) {
            $isMetapackage = $package['type'] === 'metapackage';
            $package['install-path'] = $isMetapackage ? null : '../' . $package['name'];
            $installed[] = $package;
            if ($createDirectories && ! $isMetapackage) {
                (new Filesystem())->mkdir($this->root . '/vendor/' . $package['name'], 0775);
            }
        }
        file_put_contents($this->root . '/vendor/composer/installed.json', json_encode([
            'packages'          => $installed,
            'dev-package-names' => $devPackageNames ?? array_map(
                static fn (array $package): string => $package['name'],
                $lockDevPackages,
            ),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: int, 1: string} exit code and everything the script printed
     */
    private function runCheck(): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/check-vendor-matches-lock.php', $this->root],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output];
    }
}
