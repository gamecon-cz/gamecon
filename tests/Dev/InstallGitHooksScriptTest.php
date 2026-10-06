<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * `bin/install-git-hooks.sh` (volá ho `make init`) zapojí `.githooks` do `core.hooksPath`.
 * Spouští se tu skutečný skript nad jednorázovým repozitářem, aby se nikdy nesáhlo na
 * konfiguraci skutečného repozitáře.
 */
class InstallGitHooksScriptTest extends TestCase
{
    private string $kořen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kořen = SPEC . '/install_hooks_test_' . uniqid('', false);
        (new Filesystem())->mkdir($this->kořen . '/bin', 0775);
        copy(dirname(__DIR__, 2) . '/bin/install-git-hooks.sh', $this->kořen . '/bin/install-git-hooks.sh');
        chmod($this->kořen . '/bin/install-git-hooks.sh', 0755);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kořen);

        parent::tearDown();
    }

    public function testNastaviCestuKdyzJeHookARepozitar(): void
    {
        $this->repozitarSHookem();

        [$kod, $vystup] = $this->spust();

        self::assertSame(0, $kod, $vystup);
        self::assertSame('.githooks', $this->hooksPath());
    }

    public function testOpakovaneSpusteniNicNemeni(): void
    {
        $this->repozitarSHookem();
        $this->spust();

        [$kod, $vystup] = $this->spust();

        self::assertSame(0, $kod, $vystup);
        self::assertSame('.githooks', $this->hooksPath());
        self::assertStringContainsString('leaving it', $vystup);
    }

    public function testCestuZvolenouSchvalneNeprepise(): void
    {
        $this->repozitarSHookem();
        $this->git('config', '--local', 'core.hooksPath', '/jinde');

        [$kod] = $this->spust();

        self::assertSame(0, $kod);
        self::assertSame('/jinde', $this->hooksPath());
    }

    /**
     * Relativní cesta se hledá v každém checkoutu zvlášť a chybějící složku git mlčky
     * přeskočí. Větev bez `.githooks/` by tak po zapojení přišla i o hook z globální konfigurace.
     */
    public function testBezSouboruHookuNicNezapojiAAleUpozorni(): void
    {
        $this->git('init', '--quiet');

        [$kod, $vystup] = $this->spust();

        self::assertSame(0, $kod, $vystup);
        self::assertSame('', $this->hooksPath());
        self::assertStringContainsString('skipping', $vystup);
    }

    public function testMimoRepozitarNespadne(): void
    {
        // Hook je na místě, takže jen kontrola repozitáře brání tomu, aby `git config --local` spadl.
        (new Filesystem())->mkdir($this->kořen . '/.githooks', 0775);
        file_put_contents($this->kořen . '/.githooks/prepare-commit-msg', "#!/bin/sh\n");

        [$kod, $vystup] = $this->spust();

        self::assertSame(0, $kod, $vystup);
        self::assertStringContainsString('skipping', $vystup);
    }

    public function testUpozorniNaSdilenouKonfiguraciWorktree(): void
    {
        $this->repozitarSHookem();

        [, $vystup] = $this->spust();

        self::assertStringContainsString('worktree', $vystup);
    }

    private function repozitarSHookem(): void
    {
        $this->git('init', '--quiet');
        (new Filesystem())->mkdir($this->kořen . '/.githooks', 0775);
        file_put_contents($this->kořen . '/.githooks/prepare-commit-msg', "#!/bin/sh\n");
    }

    /**
     * @return array{int, string}
     */
    private function spust(): array
    {
        return $this->proces(['bash', $this->kořen . '/bin/install-git-hooks.sh']);
    }

    private function hooksPath(): string
    {
        [, $vystup] = $this->proces(['git', 'config', '--local', '--get', 'core.hooksPath']);

        return trim($vystup);
    }

    private function git(string ...$argumenty): void
    {
        [$kod, $vystup] = $this->proces(['git', ...$argumenty]);
        self::assertSame(0, $kod, 'git ' . implode(' ', $argumenty) . ': ' . $vystup);
    }

    /**
     * @param list<string> $prikaz
     *
     * @return array{int, string}
     */
    private function proces(array $prikaz): array
    {
        $proces = proc_open($prikaz, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $roury, $this->kořen, [
            // Ať se nepoužije konfigurace z domovského adresáře, který by test ovlivnil.
            'GIT_CONFIG_GLOBAL'   => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            // Jednorázový adresář leží uvnitř projektu, ať si git nenajde jeho repozitář a netvrdí, že je v repozitáři.
            'GIT_CEILING_DIRECTORIES' => dirname((string) realpath($this->kořen)),
            'PATH'                    => (string) getenv('PATH'),
            'HOME'                    => $this->kořen,
        ]);
        self::assertIsResource($proces);
        $vystup = (string) stream_get_contents($roury[1]) . (string) stream_get_contents($roury[2]);

        return [proc_close($proces), $vystup];
    }
}
