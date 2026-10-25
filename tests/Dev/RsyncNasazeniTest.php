<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Deployment\CliRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/../../udrzba/_pomocne.php';

/**
 * The deploy runs in GitHub Actions and the container has no rsync, so the command runs against a fake `rsync` that records what it was given.
 */
class RsyncNasazeniTest extends TestCase
{
    private const array ENV_NAMES = [
        'PATH',
        'DEPLOY_SSH_KEY',
        'DEPLOY_SSH_KNOWN_HOSTS',
        'DEPLOY_SSH_PORT',
        'DEPLOY_RSYNC_MAX_DELETE_PROJECT',
        'DEPLOY_RSYNC_MAX_DELETE_VENDOR',
    ];

    private string $workDir;
    /**
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/gamecon-rsync-test-' . uniqid('', true);
        (new Filesystem())->mkdir($this->workDir . '/bin', 0775);
        foreach (self::ENV_NAMES as $name) {
            $this->originalEnv[$name] = getenv($name);
            if ($name !== 'PATH') {
                putenv($name); // a limit exported in the shell or the CI must not leak into the defaults under test
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            putenv($value === false
                ? $name
                : "{$name}={$value}");
        }
        (new Filesystem())->remove($this->workDir);
    }

    /**
     * @test
     */
    public function lettersBecomeCaseClassesWhileWildcardsAndPunctuationStay(): void
    {
        self::assertSame('/[aA]-[bB]*?[cC]', foldRsyncPatternCase('/a-B*?c'));
        self::assertSame('.[gG][iI][tT]*', foldRsyncPatternCase('.git*'));
    }

    /**
     * @test
     */
    public function anExceptionComesBeforeTheMaskItOverridesAndBringsBackItsParents(): void
    {
        $filtry = buildRsyncFilters(['/backup/*', '!/backup/.htaccess']);

        self::assertSame(
            [
                '+ ' . foldRsyncPatternCase('/backup/.htaccess'),
                '+ ' . foldRsyncPatternCase('/backup/'),
                '- ' . foldRsyncPatternCase('/backup/*'),
            ],
            $filtry,
        );
    }

    /**
     * @test
     */
    public function theLaterMaskWinsLikeInFtpDeploymentSoTheOrderIsReversed(): void
    {
        $filtry = buildRsyncFilters(['/a', '/b', '!/a']);

        self::assertSame(
            ['+ ' . foldRsyncPatternCase('/a'), '- ' . foldRsyncPatternCase('/b'), '- ' . foldRsyncPatternCase('/a')],
            $filtry,
        );
    }

    /**
     * @test
     */
    public function everyMaskWithASlashIsAnchoredInTheRootEvenWithJustATrailingOne(): void
    {
        $filtry = buildRsyncFilters(['.git*', 'web/soubory/obsah/*', '/logy', 'cache/']);

        self::assertSame(
            [
                '- ' . foldRsyncPatternCase('/cache/'),
                '- ' . foldRsyncPatternCase('/logy'),
                '- ' . foldRsyncPatternCase('/web/soubory/obsah/*'),
                '- ' . foldRsyncPatternCase('.git*'),
            ],
            $filtry,
        );
    }

    /**
     * @test
     */
    public function protectedPathsComeFirstAreNotFoldedAndRepeatedRulesAreDropped(): void
    {
        $filtry = buildRsyncFilters(['/tests', '!/a/x', '!/a/y'], ['/cache/*', '/web/soubory/systemove/*/*']);

        self::assertSame(
            [
                'P /cache/*',
                'P /web/soubory/systemove/*/*',
                '+ ' . foldRsyncPatternCase('/a/y'),
                '+ ' . foldRsyncPatternCase('/a/'),
                '+ ' . foldRsyncPatternCase('/a/x'),
                '- ' . foldRsyncPatternCase('/tests'),
            ],
            $filtry,
        );
    }

    /**
     * @test
     */
    public function projectMasksKeepEachExceptionRightAfterTheMaskItOverrides(): void
    {
        $masky = buildIgnoreMasks();

        self::assertSame(array_values($masky), $masky);
        self::assertSame(array_search('/backup/*', $masky, true) + 1, array_search('!/backup/.htaccess', $masky, true));
    }

    /**
     * @test
     */
    public function defaultMasksEqualTheOnesFtpDeploymentAddsItself(): void
    {
        self::assertSame((new CliRunner())->ignoreMasks, getDefaultIgnoreMasks());
    }

    /**
     * @test
     */
    public function commandVerifiesTheServerAndDeletesAfterTheUploadUpToTheLimit(): void
    {
        $prikaz = buildRsyncCommand('/repo', 'deploy@gamecon.cz:/', ['- /tests'], '/key', '/hosts', null, 500, false);

        self::assertSame('rsync', $prikaz[0]);
        self::assertContains('--delete-delay', $prikaz);
        self::assertContains('--delay-updates', $prikaz);
        self::assertNotContains('--delete', $prikaz);
        self::assertContains('--max-delete=500', $prikaz);
        self::assertContains('--stats', $prikaz);
        self::assertContains('--filter=- /tests', $prikaz);
        self::assertNotContains('--dry-run', $prikaz);
        self::assertSame('/repo/', $prikaz[count($prikaz) - 2]);
        self::assertSame('deploy@gamecon.cz:/', $prikaz[count($prikaz) - 1]);

        $ssh = $prikaz[array_search('--rsh', $prikaz, true) + 1];
        self::assertStringContainsString('-i /key', $ssh);
        self::assertStringContainsString('StrictHostKeyChecking=yes', $ssh);
        self::assertStringContainsString('UserKnownHostsFile=/hosts', $ssh);
        self::assertStringContainsString('GlobalKnownHostsFile=/dev/null', $ssh);
        self::assertStringNotContainsString(' -p ', $ssh);
    }

    /**
     * @test
     */
    public function dryRunMakesTheCommandADryRunAndPortIsPassedToSsh(): void
    {
        $prikaz = buildRsyncCommand('/repo/', 'x:/', [], '/key', '/hosts', 2222, null, true);

        self::assertContains('--dry-run', $prikaz);
        self::assertNotContains('--stats', $prikaz);
        self::assertStringContainsString('-p 2222', $prikaz[array_search('--rsh', $prikaz, true) + 1]);
        self::assertSame('/repo/', $prikaz[count($prikaz) - 2]);
    }

    /**
     * Real rsync makes even a --dry-run stop with exit 25 at the limit, so a plan carrying it never lists the deletions.
     *
     * @test
     */
    public function theLimitIsInTheCommandOnlyWhenOneIsGiven(): void
    {
        $withoutLimit = buildRsyncCommand('/repo', 'x:/', [], '/key', '/hosts', null, null, true);
        $withLimit = buildRsyncCommand('/repo', 'x:/', [], '/key', '/hosts', null, 0, false);

        self::assertSame([], array_filter($withoutLimit, static fn (string $argument) => str_starts_with($argument, '--max-delete')));
        self::assertContains('--max-delete=0', $withLimit);
    }

    /**
     * @test
     *
     * @dataProvider provideSshPort
     */
    public function sshPortIsReadStrictly(string $env, ?int $expected): void
    {
        putenv('DEPLOY_SSH_PORT=' . $env);

        self::assertSame($expected, getRsyncSshPort());
    }

    public static function provideSshPort(): iterable
    {
        yield 'unset means the default port' => ['', null];
        yield 'a port' => ['2222', 2222];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidSshPort
     */
    public function sshPortThatIsNotAPortIsRefused(string $env): void
    {
        putenv('DEPLOY_SSH_PORT=' . $env);

        $this->expectException(\RuntimeException::class);
        getRsyncSshPort();
    }

    public static function provideInvalidSshPort(): iterable
    {
        yield 'zero' => ['0'];
        yield 'too big' => ['65536'];
        yield 'text' => ['abc'];
        yield 'trailing space' => ['22 '];
    }

    /**
     * @test
     *
     * @dataProvider provideUntranslatableMasks
     */
    public function aMaskTheTranslationCannotSelectExactlyIsRefusedLoudly(array $masks): void
    {
        $this->expectException(\LogicException::class);
        buildRsyncFilters($masks);
    }

    public static function provideUntranslatableMasks(): iterable
    {
        yield 'bracket class' => [['/log[A-Z]']];
        yield 'backslash' => [['/a\\b']];
        yield 'double star' => [['/a/**/b']];
        yield 'exception inside a directory an earlier mask ignores' => [['/a/*', '!/a/b/c.php']];
        yield 'exception inside a directory named by an earlier mask' => [['/vendor/foo', '!/vendor/foo/bar.php']];
        yield 'exception inside a directory ignored by name' => [['cache', '!/x/cache/y.php']];
        yield 'exception under a wildcard parent that covers an ignored directory' => [['/web/soubory/systemove/x', '!/web/soubory/systemove/*/.htaccess']];
        yield 'exception inside a directory ignored with a trailing slash' => [['/dokumentace/', '!/dokumentace/x/y.php']];
    }

    /**
     * @test
     */
    public function aDirectoryThatProtectedFilesKeepIsNotCountedAsDeleted(): void
    {
        $plan = [
            'sending incremental file list',
            'cannot delete non-empty directory: web/soubory/systemove/x',
            'deleting web/soubory/systemove/x/',
            'deleting web/soubory/old.php',
            'deleting web/soubory/olddir/',
            'deleting web/soubory/systemove/xy/',
        ];

        self::assertSame(
            ['deleting web/soubory/old.php', 'deleting web/soubory/olddir/', 'deleting web/soubory/systemove/xy/'],
            getPlannedRsyncDeletions($plan),
        );
        self::assertSame(['web/soubory/systemove/x/'], getKeptRsyncDirectories($plan));
    }

    /**
     * @test
     */
    public function keptDirectoriesAreRecognisedWhateverTheirDepthOrderOrName(): void
    {
        $plan = [
            'deleting a/b/c/',
            'cannot delete non-empty directory: a/b/c',
            'deleting a/b/',
            'cannot delete non-empty directory: a/b',
            'cannot delete non-empty directory: with space/dir',
            'deleting with space/dir/',
            'deleting with space/file.php',
        ];

        self::assertSame(['deleting with space/file.php'], getPlannedRsyncDeletions($plan));
        self::assertSame(['a/b/c/', 'a/b/', 'with space/dir/'], getKeptRsyncDirectories($plan));
    }

    /**
     * @test
     */
    public function theUploadMayRefuseKeptDirectoriesWithoutHittingTheLimit(): void
    {
        $this->installFakeRsync(planOutput: "deleting a\ndeleting b\ncannot delete non-empty directory: d\ndeleting d/\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=2');

        $this->runDeploy(dryRun: false);

        self::assertNotContains('--max-delete=2', $this->readRecordedArguments(2));
        self::assertContains('--max-delete=23', $this->readRecordedArguments(2));
    }

    /**
     * @test
     *
     * @dataProvider providePlanAndTheBudgetOfTheUpload
     */
    public function theUploadMayDeleteOnlyWhatThePlanFoundPlusASmallReserveWhateverTheLimitsAre(string $planOutput, string $expectedBudget): void
    {
        $this->installFakeRsync(planOutput: $planOutput);
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=500');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=5000');

        $this->runDeploy(dryRun: false);

        $plan = $this->readRecordedArguments(1);
        self::assertSame([], array_filter($plan, static fn (string $argument) => str_starts_with($argument, '--max-delete')));
        self::assertContains($expectedBudget, $this->readRecordedArguments(2));
    }

    public static function providePlanAndTheBudgetOfTheUpload(): iterable
    {
        yield 'nothing to delete leaves only the reserve' => ["sending incremental file list\n", '--max-delete=20'];
        yield 'both areas count' => ["deleting a\ndeleting vendor/b\n", '--max-delete=22'];
        yield 'a kept directory counts too' => ["deleting a\ncannot delete non-empty directory: d\ndeleting d/\n", '--max-delete=22'];
    }

    /**
     * @test
     */
    public function theRealMasksAndProtectedPathsAreTranslatable(): void
    {
        $filtry = buildRsyncFilters(buildIgnoreMasks(), getRsyncProtectedPaths(), getDefaultIgnoreMasks());

        self::assertContains('P /cache/*', $filtry);
        self::assertContains('P /nastaveni/google-api-client-secret.json', $filtry);
        self::assertContains('H ' . foldRsyncPatternCase('.git*'), $filtry);
        self::assertNotContains('- ' . foldRsyncPatternCase('.git*'), $filtry);
        self::assertStringStartsWith('H ', $filtry[count(getRsyncProtectedPaths())]);
    }

    /**
     * @test
     */
    public function senderOnlyMasksBecomeHideRulesAfterTheProtectedPathsAndBeforeTheOtherMasks(): void
    {
        $filtry = buildRsyncFilters(['/backup/*', '!/backup/.htaccess'], ['/cache/*'], ['*.bak', '.git*']);

        self::assertSame(
            [
                'P /cache/*',
                'H ' . foldRsyncPatternCase('.git*'),
                'H ' . foldRsyncPatternCase('*.bak'),
                '+ ' . foldRsyncPatternCase('/backup/.htaccess'),
                '+ ' . foldRsyncPatternCase('/backup/'),
                '- ' . foldRsyncPatternCase('/backup/*'),
            ],
            $filtry,
        );
    }

    /**
     * @test
     */
    public function theGuardAgainstUntranslatableMasksAlsoChecksTheSenderOnlyOnes(): void
    {
        $this->expectException(\LogicException::class);
        buildRsyncFilters(['/a/*'], [], ['log[A-Z]']);
    }

    /**
     * @test
     *
     * @dataProvider provideSenderOnlyMasksThatAreNotPlainNames
     */
    public function aSenderOnlyMaskThatIsAnExceptionOrHasASlashIsRefused(string $mask): void
    {
        $this->expectException(\LogicException::class);
        buildRsyncFilters(['/a/*'], [], [$mask]);
    }

    public static function provideSenderOnlyMasksThatAreNotPlainNames(): iterable
    {
        yield 'an exception, which would lift the protection of the plain excludes' => ['!keep.bak'];
        yield 'a rooted path' => ['/backup/old.bak'];
        yield 'a path below a directory' => ['sub/old.bak'];
    }

    /**
     * @test
     *
     * @dataProvider provideServerFilesThatMatchADefaultMaskButMustStay
     */
    public function aServerFileThatMatchesADefaultMaskButMustStayIsCoveredByAnotherRule(string $path): void
    {
        $filters = buildRsyncFilters(buildIgnoreMasks(), getRsyncProtectedPaths(), getDefaultIgnoreMasks());

        self::assertTrue($this->isCoveredByAProtectOrExcludeRule($path, $filters), "{$path} would be deleted from the server");
    }

    public static function provideServerFilesThatMatchADefaultMaskButMustStay(): iterable
    {
        yield 'a backup in the uploaded content' => ['/web/soubory/obsah/rubrika/stranka/stary-soubor.bak'];
        yield 'a gitignore in the uploaded content' => ['/web/soubory/obsah/sponzori/titulka/.gitignore'];
        yield 'a gitkeep in an upload folder' => ['/web/soubory/systemove/linie/.gitkeep'];
        yield 'a gitkeep in the cache' => ['/cache/private/databaze/.gitkeep'];
        yield 'a gitkeep in the backups' => ['/backup/.gitkeep'];
    }

    /**
     * @test
     *
     * @dataProvider provideServerLeftoversThatAreDeleted
     */
    public function aLeftoverThatMatchesADefaultMaskAndNothingElseIsNotCovered(string $path): void
    {
        $filters = buildRsyncFilters(buildIgnoreMasks(), getRsyncProtectedPaths(), getDefaultIgnoreMasks());

        self::assertFalse($this->isCoveredByAProtectOrExcludeRule($path, $filters), "{$path} would stay on the server");
    }

    public static function provideServerLeftoversThatAreDeleted(): iterable
    {
        yield 'a workflow of a package' => ['/vendor/balicek/.github/workflows/ci.yaml'];
        yield 'a gitignore of a package' => ['/vendor/balicek/.gitignore'];
        yield 'a thumbnail cache' => ['/web/Thumbs.db'];
    }

    /**
     * @test
     *
     * @dataProvider provideMaxDeleteProject
     */
    public function theProjectLimitComesFromTheEnvironmentAndZeroMeansDeleteNothing(string $env, int $expected): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=' . $env);

        self::assertSame($expected, getRsyncMaxDeleteProject());
    }

    public static function provideMaxDeleteProject(): iterable
    {
        yield 'unset uses the default' => ['', 500];
        yield 'zero is a real limit, not the default' => ['0', 0];
        yield 'a number' => ['25', 25];
    }

    /**
     * @test
     *
     * @dataProvider provideMaxDeleteVendor
     */
    public function theVendorLimitHasItsOwnVariableAndAHigherDefault(string $env, int $expected): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=' . $env);

        self::assertSame($expected, getRsyncMaxDeleteVendor());
    }

    public static function provideMaxDeleteVendor(): iterable
    {
        yield 'unset uses the default' => ['', 5000];
        yield 'zero is a real limit, not the default' => ['0', 0];
        yield 'a number' => ['12000', 12000];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidMaxDelete
     */
    public function aVendorLimitThatIsNotAWholeNumberIsRefused(string $env): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=' . $env);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~^DEPLOY_RSYNC_MAX_DELETE_VENDOR musí~');
        getRsyncMaxDeleteVendor();
    }

    /**
     * @test
     */
    public function anInvalidVendorLimitStopsTheRunBeforeRsyncIsCalled(): void
    {
        $this->installFakeRsync();
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=lots');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DEPLOY_RSYNC_MAX_DELETE_VENDOR');
        try {
            $this->runDeploy(dryRun: false);
        } finally {
            self::assertFileDoesNotExist($this->workDir . '/calls');
        }
    }

    /**
     * @test
     */
    public function deletionsAreSplitIntoVendorAndTheRestByTheTopLevelDirectory(): void
    {
        $split = splitRsyncDeletionsByVendor([
            'deleting vendor/a/b.php',
            'deleting vendor/',
            'deleting vendorish/x.php',
            'deleting web/vendor/x.js',
            'deleting symfony/vendor/y.php',
            'deleting web/x.php',
        ]);

        self::assertSame(['deleting vendor/a/b.php', 'deleting vendor/'], $split['vendor']);
        self::assertSame(
            ['deleting vendorish/x.php', 'deleting web/vendor/x.js', 'deleting symfony/vendor/y.php', 'deleting web/x.php'],
            $split['project'],
        );
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidMaxDelete
     */
    public function aProjectLimitThatIsNotAWholeNumberIsRefused(string $env): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=' . $env);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~^DEPLOY_RSYNC_MAX_DELETE_PROJECT musí~');
        getRsyncMaxDeleteProject();
    }

    public static function provideInvalidMaxDelete(): iterable
    {
        yield 'negative means no limit to rsync' => ['-1'];
        yield 'text' => ['lots'];
        yield 'decimal' => ['1.5'];
        yield 'so big that it would turn into the maximum integer' => ['99999999999999999999'];
    }

    /**
     * @test
     */
    public function withoutKnownHostsNothingIsRun(): void
    {
        $this->installFakeRsync();
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DEPLOY_SSH_KNOWN_HOSTS');
        try {
            uploadFilesViaRsync('/repo', [], 'x:/', false);
        } finally {
            self::assertFileDoesNotExist($this->workDir . '/calls');
        }
    }

    /**
     * @test
     */
    public function aRealRunPlansFirstThenUploadsAndHandsKeyAndKnownHostsToSshAsFilesRemovedAfterwards(): void
    {
        $this->installFakeRsync(planOutput: "sending incremental file list\ndeleting old/a.php\n");
        putenv("DEPLOY_SSH_KEY=-----BEGIN-----\r\ntajny\r\n-----END-----");
        putenv('DEPLOY_SSH_KNOWN_HOSTS=gamecon.cz ssh-ed25519 AAAA');
        putenv('DEPLOY_SSH_PORT=2222');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=77');

        $this->runDeploy(dryRun: false);

        $plan = $this->readRecordedArguments(1);
        $upload = $this->readRecordedArguments(2);
        self::assertContains('--dry-run', $plan);
        self::assertNotContains('--dry-run', $upload);
        self::assertNotContains('--max-delete=21', $plan);
        self::assertContains('--max-delete=21', $upload);
        foreach ([$plan, $upload] as $argumenty) {
            self::assertContains('--filter=P /cache/*', $argumenty);
            self::assertContains('--filter=H ' . foldRsyncPatternCase('.git*'), $argumenty);
            self::assertNotContains('--filter=- ' . foldRsyncPatternCase('.git*'), $argumenty);
            self::assertContains('x:/', $argumenty);
            self::assertStringContainsString('-p 2222', implode(' ', $argumenty));
        }
        self::assertSame("-----BEGIN-----\ntajny\n-----END-----\n", file_get_contents($this->workDir . '/seen-key'));
        self::assertSame('600', trim((string) file_get_contents($this->workDir . '/seen-key-mode')));
        self::assertSame("gamecon.cz ssh-ed25519 AAAA\n", file_get_contents($this->workDir . '/seen-known-hosts'));
        self::assertFileDoesNotExist(trim((string) file_get_contents($this->workDir . '/key-path')));
        self::assertFileDoesNotExist(trim((string) file_get_contents($this->workDir . '/known-hosts-path')));
    }

    /**
     * @test
     */
    public function aDryRunIsOneDryRunCommandAndNeverTheUpload(): void
    {
        $this->installFakeRsync();
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');

        $this->runDeploy(dryRun: true);

        self::assertContains('--dry-run', $this->readRecordedArguments(1));
        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * @test
     */
    public function tooManyPlannedDeletionsStopTheRunBeforeAnythingChanges(): void
    {
        $this->installFakeRsync(planOutput: "deleting a\ndeleting b\ndeleting c\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=2');

        try {
            $this->runDeploy(dryRun: false);
            self::fail('Deleting 3 files with a limit of 2 must stop the deploy');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('3 položek', $exception->getMessage());
            self::assertStringContainsString('nic se nezměnilo', $exception->getMessage());
        }

        self::assertFileExists($this->workDir . '/arguments-1');
        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
        self::assertFileDoesNotExist(trim((string) file_get_contents($this->workDir . '/key-path')));
    }

    /**
     * @test
     */
    public function aDryRunFailsTooWhenTheRealRunWouldBeStoppedByTheLimit(): void
    {
        $this->installFakeRsync(planOutput: "deleting a\ndeleting b\ndeleting c\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=2');

        try {
            $this->runDeploy(dryRun: true);
            self::fail('A dry run that lists more deletions than allowed must fail, like the real run would');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('3 položek', $exception->getMessage());
        }

        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * @test
     */
    public function manyVendorDeletionsPassTheirOwnHigherLimitWhileTheRestIsHeldToTheLowerOne(): void
    {
        $this->installFakeRsync(planOutput: "deleting vendor/a.php\ndeleting vendor/b.php\ndeleting vendor/c/\ndeleting web/x.php\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=1');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=30');

        $this->runDeploy(dryRun: false);

        self::assertFileExists($this->workDir . '/arguments-2');
        self::assertContains('--max-delete=24', $this->readRecordedArguments(2));
    }

    /**
     * @test
     */
    public function tooManyDeletionsOutsideVendorStopTheRunEvenWhenVendorHasRoom(): void
    {
        $this->installFakeRsync(planOutput: "deleting a\ndeleting b\ndeleting vendor/x\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=1');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=100');

        try {
            $this->runDeploy(dryRun: false);
            self::fail('Two deletions outside vendor/ with a limit of 1 must stop the deploy');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('2 položek mimo vendor/', $exception->getMessage());
            self::assertStringContainsString('DEPLOY_RSYNC_MAX_DELETE_PROJECT (1)', $exception->getMessage());
            self::assertStringNotContainsString('DEPLOY_RSYNC_MAX_DELETE_VENDOR', $exception->getMessage());
            self::assertStringContainsString('chráněné cesty', $exception->getMessage());
            self::assertStringContainsString('nic se nezměnilo', $exception->getMessage());
        }

        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * @test
     */
    public function tooManyVendorDeletionsStopTheRunEvenWhenTheRestHasRoom(): void
    {
        $this->installFakeRsync(planOutput: "deleting vendor/a\ndeleting vendor/b\ndeleting vendor/c\ndeleting web/x.php\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=100');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=2');

        try {
            $this->runDeploy(dryRun: false);
            self::fail('Three deletions in vendor/ with a limit of 2 must stop the deploy');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('3 položek ve vendor/', $exception->getMessage());
            self::assertStringContainsString('DEPLOY_RSYNC_MAX_DELETE_VENDOR (2)', $exception->getMessage());
            self::assertStringContainsString('závislostech', $exception->getMessage());
            self::assertStringNotContainsString('položek mimo vendor/', $exception->getMessage());
            self::assertStringNotContainsString('chráněné cesty', $exception->getMessage());
        }

        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * @test
     */
    public function bothLimitsAreNamedWhenBothAreExceeded(): void
    {
        $this->installFakeRsync(planOutput: "deleting vendor/a\ndeleting vendor/b\ndeleting x\ndeleting y\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=1');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~2 položek mimo vendor/.*DEPLOY_RSYNC_MAX_DELETE_PROJECT \(1\).*2 položek ve vendor/.*DEPLOY_RSYNC_MAX_DELETE_VENDOR \(1\)~');
        $this->runDeploy(dryRun: false);
    }

    /**
     * @test
     */
    public function aDirectoryUnderVendorThatProtectedFilesKeepIsNotCountedAgainstTheVendorLimit(): void
    {
        $this->installFakeRsync(planOutput: "cannot delete non-empty directory: vendor/x\ndeleting vendor/x/\ndeleting vendor/y.php\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=0');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=1');

        $this->runDeploy(dryRun: false);

        self::assertContains('--max-delete=22', $this->readRecordedArguments(2));
    }

    /**
     * @test
     */
    public function aDryRunFailsTooWhenTheVendorLimitWouldStopTheRealRun(): void
    {
        $this->installFakeRsync(planOutput: "deleting vendor/a\ndeleting vendor/b\ndeleting vendor/c\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=2');

        try {
            $this->runDeploy(dryRun: true);
            self::fail('A dry run that lists more vendor/ deletions than allowed must fail, like the real run would');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('3 položek ve vendor/', $exception->getMessage());
        }

        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * @test
     */
    public function theListedDeletionsStartOutsideVendorSoThatWhatTrippedTheLimitIsNotCutOff(): void
    {
        $plan = [];
        for ($index = 1; $index <= 60; ++$index) {
            $plan[] = "deleting vendor/pkg/file{$index}.php";
        }
        $plan[] = 'deleting web/a.php';
        $plan[] = 'deleting web/b.php';
        $this->installFakeRsync(planOutput: implode("\n", $plan) . "\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_PROJECT=1');

        ob_start();
        try {
            uploadFilesViaRsync('/repo', ['/tests'], 'x:/', false);
            self::fail('Two deletions outside vendor/ with a limit of 1 must stop the deploy');
        } catch (\RuntimeException) {
            // expected, what matters is what was printed before it
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertStringContainsString('deleting web/a.php', $output);
        self::assertStringContainsString('deleting web/b.php', $output);
        self::assertStringContainsString('… a dalších 12', $output);
    }

    /**
     * @test
     */
    public function theListedDeletionsStartWithTheVendorAreaWhenOnlyItTrippedItsLimit(): void
    {
        $plan = [];
        for ($index = 1; $index <= 60; ++$index) {
            $plan[] = "deleting web/file{$index}.php";
        }
        $plan[] = 'deleting vendor/pkg/a.php';
        $plan[] = 'deleting vendor/pkg/b.php';
        $plan[] = 'deleting vendor/pkg/c.php';
        $this->installFakeRsync(planOutput: implode("\n", $plan) . "\n");
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');
        putenv('DEPLOY_RSYNC_MAX_DELETE_VENDOR=2');

        ob_start();
        try {
            uploadFilesViaRsync('/repo', ['/tests'], 'x:/', false);
            self::fail('Three deletions in vendor/ with a limit of 2 must stop the deploy');
        } catch (\RuntimeException) {
            // expected, what matters is what was printed before it
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertStringContainsString('deleting vendor/pkg/a.php', $output);
        self::assertStringContainsString('deleting vendor/pkg/c.php', $output);
        self::assertStringContainsString('… a dalších 13', $output);
    }

    /**
     * @test
     */
    public function aFailedUploadStopsTheDeployAndStillRemovesTheKey(): void
    {
        $this->installFakeRsync(exitCodes: [0, 23]);
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');

        try {
            $this->runDeploy(dryRun: false);
            self::fail('A failing rsync must stop the deploy before migrations run');
        } catch (\Exception $exception) {
            self::assertSame(23, $exception->getCode());
        }

        self::assertFileExists($this->workDir . '/arguments-2');
        self::assertFileDoesNotExist(trim((string) file_get_contents($this->workDir . '/key-path')));
    }

    /**
     * @test
     */
    public function aFailedPlanStopsTheDeployBeforeTheUpload(): void
    {
        $this->installFakeRsync(exitCodes: [255]);
        putenv('DEPLOY_SSH_KEY=klic');
        putenv('DEPLOY_SSH_KNOWN_HOSTS=hosts');

        try {
            $this->runDeploy(dryRun: false);
            self::fail('A server that cannot be reached or verified must stop the deploy');
        } catch (\Exception $exception) {
            self::assertSame(255, $exception->getCode());
        }

        self::assertFileDoesNotExist($this->workDir . '/arguments-2');
    }

    /**
     * Walks the path from its top directory down like the receiving rsync: the first rule that matches a node decides,
     * `H` rules are for the sender only, so they are skipped. Close enough to rsync for the rules of this project.
     *
     * @param list<string> $filters
     */
    private function isCoveredByAProtectOrExcludeRule(string $path, array $filters): bool
    {
        $parts = explode('/', trim($path, '/'));
        for ($depth = 1; $depth <= count($parts); ++$depth) {
            $node = '/' . implode('/', array_slice($parts, 0, $depth));
            $isDir = $depth < count($parts);
            foreach ($filters as $filter) {
                [$type, $pattern] = explode(' ', $filter, 2);
                if ($type === 'H' || ! $this->ruleMatchesNode($pattern, $node, $isDir)) {
                    continue;
                }
                if ($type !== '+') {
                    return true;
                }
                break;
            }
        }

        return false;
    }

    private function ruleMatchesNode(string $pattern, string $node, bool $isDir): bool
    {
        if (str_ends_with($pattern, '/***')) {
            return fnmatch(substr($pattern, 0, -4), $node, FNM_PATHNAME);
        }
        if (str_ends_with($pattern, '/')) {
            return $isDir && fnmatch(rtrim($pattern, '/'), $node, FNM_PATHNAME);
        }
        if (! str_contains($pattern, '/')) {
            return fnmatch($pattern, basename($node));
        }

        return fnmatch($pattern, $node, FNM_PATHNAME);
    }

    private function runDeploy(bool $dryRun): void
    {
        ob_start();
        try {
            uploadFilesViaRsync('/repo', ['/tests'], 'x:/', $dryRun);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * @param list<int> $exitCodes one per call, the last one repeats
     */
    private function installFakeRsync(array $exitCodes = [0], string $planOutput = ''): void
    {
        file_put_contents($this->workDir . '/exit-codes', implode("\n", $exitCodes) . "\n");
        file_put_contents($this->workDir . '/plan-output', $planOutput);
        $script = <<<'BASH'
            #!/usr/bin/env bash
            work=@WORK@
            calls=$(cat "$work/calls" 2>/dev/null || echo 0)
            calls=$((calls + 1))
            echo "$calls" > "$work/calls"
            printf '%s\n' "$@" > "$work/arguments-$calls"
            rsh=""
            previous=""
            for argument in "$@"; do
                if [ "$previous" = "--rsh" ]; then rsh="$argument"; fi
                previous="$argument"
            done
            key=$(echo "$rsh" | sed -E 's/.* -i ([^ ]+).*/\1/')
            hosts=$(echo "$rsh" | sed -E 's/.*UserKnownHostsFile=([^ ]+).*/\1/')
            echo "$key" > "$work/key-path"
            echo "$hosts" > "$work/known-hosts-path"
            cp "$key" "$work/seen-key"
            cp "$hosts" "$work/seen-known-hosts"
            stat -c '%a' "$key" > "$work/seen-key-mode"
            if printf '%s\n' "$@" | grep -qx -e '--dry-run'; then
                # real rsync stops even a dry run at --max-delete with exit 25, after listing only that many deletions
                max=$(printf '%s\n' "$@" | sed -n 's/^--max-delete=//p')
                total=$(grep -c '^deleting ' "$work/plan-output")
                if [ -n "$max" ] && [ "$total" -gt "$max" ]; then
                    grep '^deleting ' "$work/plan-output" | head -n "$max"
                    echo "Deletions stopped due to --max-delete limit ($((total - max)) skipped)"
                    exit 25
                fi
                cat "$work/plan-output"
            fi
            code=$(sed -n "${calls}p" "$work/exit-codes")
            [ -z "$code" ] && code=$(tail -n 1 "$work/exit-codes")
            exit "$code"
            BASH;
        file_put_contents($this->workDir . '/bin/rsync', str_replace('@WORK@', $this->workDir, $script) . "\n");
        chmod($this->workDir . '/bin/rsync', 0755);
        putenv('PATH=' . $this->workDir . '/bin:' . $this->originalEnv['PATH']);
    }

    /**
     * @return list<string>
     */
    private function readRecordedArguments(int $call): array
    {
        return explode("\n", rtrim((string) file_get_contents($this->workDir . '/arguments-' . $call), "\n"));
    }
}
