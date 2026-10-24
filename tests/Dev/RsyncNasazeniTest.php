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
        'DEPLOY_RSYNC_MAX_DELETE',
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
        putenv('DEPLOY_RSYNC_MAX_DELETE=2');

        $this->runDeploy(dryRun: false);

        self::assertNotContains('--max-delete=2', $this->readRecordedArguments(2));
        self::assertContains('--max-delete=3', $this->readRecordedArguments(2));
    }

    /**
     * @test
     */
    public function theRealMasksAndProtectedPathsAreTranslatable(): void
    {
        $filtry = buildRsyncFilters(array_merge(buildIgnoreMasks(), getDefaultIgnoreMasks()), getRsyncProtectedPaths());

        self::assertContains('P /cache/*', $filtry);
        self::assertContains('P /nastaveni/google-api-client-secret.json', $filtry);
    }

    /**
     * @test
     *
     * @dataProvider provideMaxDelete
     */
    public function maxDeleteComesFromTheEnvironmentAndZeroMeansDeleteNothing(string $env, int $expected): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE=' . $env);

        self::assertSame($expected, getRsyncMaxDelete());
    }

    public static function provideMaxDelete(): iterable
    {
        yield 'unset uses the default' => ['', 1000];
        yield 'zero is a real limit, not the default' => ['0', 0];
        yield 'a number' => ['25', 25];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidMaxDelete
     */
    public function maxDeleteThatIsNotAWholeNumberIsRefused(string $env): void
    {
        putenv('DEPLOY_RSYNC_MAX_DELETE=' . $env);

        $this->expectException(\RuntimeException::class);
        getRsyncMaxDelete();
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
        putenv('DEPLOY_RSYNC_MAX_DELETE=77');

        $this->runDeploy(dryRun: false);

        $plan = $this->readRecordedArguments(1);
        $upload = $this->readRecordedArguments(2);
        self::assertContains('--dry-run', $plan);
        self::assertNotContains('--dry-run', $upload);
        self::assertNotContains('--max-delete=77', $plan);
        self::assertContains('--max-delete=77', $upload);
        foreach ([$plan, $upload] as $argumenty) {
            self::assertContains('--filter=P /cache/*', $argumenty);
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
        putenv('DEPLOY_RSYNC_MAX_DELETE=2');

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
        putenv('DEPLOY_RSYNC_MAX_DELETE=2');

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
