<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Hook `.githooks/prepare-commit-msg` doplňuje do předmětu commitu úkol z názvu větve. Kdo ho
 * nemá, commituje bez prefixu a nic ho neupozorní, takže chování drží tenhle test: spouští
 * skutečný hook nad jednorázovým repozitářem.
 */
class PrepareCommitMsgHookTest extends TestCase
{
    private string $repozitar;

    private ?string $adresarStinovehoGrepu = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repozitar = SPEC . '/hook_test_' . uniqid('', false);
        (new Filesystem())->mkdir($this->repozitar, 0775);
        $this->git('init', '--quiet');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->repozitar);

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function prefixyProvider(): iterable
    {
        yield 'karta z Trella: holé číslo' => ['1274-prepsat-e-shop', 'Commit the harness', '1274 Commit the harness'];
        yield 'issue: gh- velkými' => ['gh-1090-upgradovat-vite-na-v8', 'Upgrade vite to v8', 'GH-1090 Upgrade vite to v8'];
        yield 'větev už velkými' => ['GH-1090-neco', 'Upgrade', 'GH-1090 Upgrade'];
        yield 'větev z PR' => ['pr/1274-neco', 'Upgrade', '1274 Upgrade'];
    }

    /**
     * @dataProvider prefixyProvider
     */
    public function testDoplniUkolZNazvuVetve(string $vetev, string $zprava, string $ocekavano): void
    {
        $this->naVetvi($vetev);

        self::assertSame($ocekavano, $this->spust($zprava));
    }

    public function testUzPrefixovanyPredmetNezdvoji(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('GH-1090 Upgrade', $this->spust('GH-1090 Upgrade'));
    }

    /**
     * Prefix napsaný malými písmeny se pozná jako už přítomný, ne zdvojí.
     */
    public function testPrefixMalymiPismenyNezdvoji(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('gh-1090 Upgrade', $this->spust('gh-1090 Upgrade'));
    }

    /**
     * Předmět, který úkol jen zmiňuje uprostřed, prefix pořád potřebuje: pravidlo „už je v předmětu"
     * patří jen revertu, který ho nese po vracené změně.
     */
    public function testPredmetKterySvujUkolJenZminujeDostanePrefix(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('GH-1090 Oprava GH-1090 uprostred', $this->spust('Oprava GH-1090 uprostred'));
        self::assertSame('GH-1090 Foo 1090 bar', $this->spust('Foo 1090 bar'));
    }

    public function testRevertSVlastnimUkolemHoNezdvoji(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('Revert "GH-1090 Foo"', $this->spust('Revert "GH-1090 Foo"'));
    }

    public function testRevertSJinymUkolemDostanePrefix(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('GH-1090 Revert "GH-9 Foo"', $this->spust('Revert "GH-9 Foo"'));
    }

    public function testSquashAAmendSeNemeni(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('squash! GH-1090 Foo', $this->spust('squash! GH-1090 Foo'));
        self::assertSame('amend! GH-1090 Foo', $this->spust('amend! GH-1090 Foo'));
    }

    public function testFixupSeNemeni(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('fixup! GH-1090 Upgrade', $this->spust('fixup! GH-1090 Upgrade'));
    }

    public function testViceradkovaZpravaZachovaTeloAPrefixujeJenPredmet(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame("GH-1090 Upgrade\n\nDůvod změny.", $this->spust("Upgrade\n\nDůvod změny."));
    }

    public function testOddelenaHlavaBezVetveSeNemeni(): void
    {
        $this->naVetvi('gh-1090-neco');
        $this->git('update-ref', '--no-deref', 'HEAD', trim($this->git('rev-parse', 'HEAD')));

        self::assertSame('Upgrade', $this->spust('Upgrade'));
    }

    /**
     * První commit na čerstvé větvi: `HEAD` ještě nikam neukazuje, ale jméno větve už je známé.
     */
    public function testPrvniCommitNaNovaVetviDostanePrefix(): void
    {
        $this->git('symbolic-ref', 'HEAD', 'refs/heads/gh-5-nova');

        self::assertSame('GH-5 Prvni', $this->spust('Prvni'));
    }

    public function testVetevBezCislaSeNemeni(): void
    {
        $this->naVetvi('main');

        self::assertSame('Upgrade', $this->spust('Upgrade'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function zdrojeKtereSeNemeniProvider(): iterable
    {
        yield 'sloučení' => ['merge'];
        yield 'oprava posledního commitu, -c nebo -C' => ['commit'];
        yield 'squash' => ['squash'];
    }

    /**
     * Jen nová zpráva zadaná při commitu (`message`, `template` nebo bez zdroje) se doplňuje;
     * při úpravě existujícího commitu by se prefix doplňoval znovu.
     *
     * @dataProvider zdrojeKtereSeNemeniProvider
     */
    public function testZpravuZJinehoZdrojeNemeni(string $zdroj): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('Upgrade', $this->spust('Upgrade', $zdroj));
    }

    public function testZdrojMessageADefaultTemplateSeDoplni(): void
    {
        $this->naVetvi('gh-1090-neco');

        self::assertSame('GH-1090 Upgrade', $this->spust('Upgrade', 'message'));
        self::assertSame('GH-1090 Upgrade', $this->spust('Upgrade', 'template'));
    }

    /**
     * BusyBox grep (Alpine) zná jen krátké přepínače. S dlouhými by hook jen vypsal chybu, skončil
     * s nulou a commit by prošel bez prefixu, takže by na to nic neupozornilo.
     */
    public function testFungujeSGrepemKteryZnaJenKratkePrepinace(): void
    {
        $this->naVetvi('gh-1090-neco');
        $this->nahradGrepJenSKratkymiPrepinaci();

        self::assertSame('GH-1090 Upgrade', $this->spust('Upgrade'));
        self::assertSame('fixup! GH-1090 Upgrade', $this->spust('fixup! GH-1090 Upgrade'));
        self::assertSame('GH-1090 Upgrade', $this->spust('GH-1090 Upgrade'));
        self::assertSame('Revert "GH-1090 Foo"', $this->spust('Revert "GH-1090 Foo"'));

        file_put_contents($this->repozitar . '/.git/CHERRY_PICK_HEAD', trim($this->git('rev-parse', 'HEAD')) . "\n");
        self::assertSame('GH-9 Foo', $this->spust('GH-9 Foo'));
    }

    /**
     * Commit z plumbingu, aby vznikl bez spuštění jakéhokoli hooku.
     */
    private function naVetvi(string $vetev): void
    {
        $strom = trim($this->git('hash-object', '-t', 'tree', '/dev/null'));
        $commit = trim($this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit-tree', $strom, '-m', 'init'));
        $this->git('update-ref', 'refs/heads/' . $vetev, $commit);
        $this->git('symbolic-ref', 'HEAD', 'refs/heads/' . $vetev);
    }

    private function nahradGrepJenSKratkymiPrepinaci(): void
    {
        $adresar = $this->repozitar . '/busybox';
        (new Filesystem())->mkdir($adresar, 0775);
        $skript = <<<'SH'
        #!/bin/sh
        for argument in "$@"; do
            case "$argument" in --*) echo "grep: unrecognized option: $argument" >&2; exit 2 ;; esac
        done
        PATH="${PATH#__ADRESAR__:}" exec grep "$@"
        SH;
        file_put_contents($adresar . '/grep', str_replace('__ADRESAR__', $adresar, $skript) . "\n");
        chmod($adresar . '/grep', 0755);
        $this->adresarStinovehoGrepu = $adresar;
    }

    private function spust(string $zprava, ?string $zdroj = null): string
    {
        $soubor = $this->repozitar . '/COMMIT_EDITMSG';
        file_put_contents($soubor, $zprava);

        $hook = dirname(__DIR__, 2) . '/.githooks/prepare-commit-msg';
        self::assertFileExists($hook);
        $prikaz = array_filter(['sh', $hook, $soubor, $zdroj], static fn (?string $cast): bool => $cast !== null);
        $prostredi = $this->adresarStinovehoGrepu === null
            ? null
            : [
                ...getenv(),
                'PATH' => $this->adresarStinovehoGrepu . ':' . getenv('PATH'),
            ];
        $proces = proc_open($prikaz, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $roury, $this->repozitar, $prostredi);
        self::assertIsResource($proces);
        stream_get_contents($roury[1]);
        stream_get_contents($roury[2]);
        self::assertSame(0, proc_close($proces));

        return trim((string) file_get_contents($soubor));
    }

    private function git(string ...$argumenty): string
    {
        $proces = proc_open(['git', ...$argumenty], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $roury, $this->repozitar);
        self::assertIsResource($proces);
        $vystup = (string) stream_get_contents($roury[1]);
        $chyby = (string) stream_get_contents($roury[2]);
        $kod = proc_close($proces);
        self::assertSame(0, $kod, 'git ' . implode(' ', $argumenty) . ': ' . $chyby);

        return $vystup;
    }
}
