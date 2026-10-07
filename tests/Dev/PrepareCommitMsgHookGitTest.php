<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * `.githooks/prepare-commit-msg` proti skutečnému gitu. Některé situace (cherry-pick, revert,
 * rebase, autosquash) se jen napodobit nedají: hook v nich dostává jiný zdroj zprávy a jiný
 * stav repozitáře, než by tušil test, který ho volá ručně.
 */
class PrepareCommitMsgHookGitTest extends TestCase
{
    private string $repozitar;

    private int $citac = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repozitar = SPEC . '/hook_git_test_' . uniqid('', false);
        (new Filesystem())->mkdir($this->repozitar, 0775);
        $this->git('init', '--quiet', '-b', 'main');
        $this->git('config', 'user.name', 'Test');
        $this->git('config', 'user.email', 'test@example.invalid');
        // Až po prvním commitu, který je jen fixture; od té chvíle hook běží u všeho.
        $this->commitBezHooku('root');
        $this->git('config', 'core.hooksPath', dirname(__DIR__, 2) . '/.githooks');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->repozitar);

        parent::tearDown();
    }

    public function testBeznyCommitDostanePrefixZVetve(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite');

        $this->commit('Upgrade vite');

        self::assertSame('GH-1090 Upgrade vite', $this->predmet());
    }

    /**
     * Cherry-pick nese původní předmět i s jeho úkolem; druhý prefix by ho jen zdvojil.
     */
    public function testCherryPickPrefixovanehoCommituPredmetNemeni(): void
    {
        $this->git('switch', '--quiet', '-c', '1274-eshop');
        $this->commit('Feature');
        $puvodni = $this->hlava();
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite', 'main');

        $this->git('cherry-pick', $puvodni);

        self::assertSame('1274 Feature', $this->predmet());
    }

    public function testCherryPickPrefixovanehoCommituSDashXPredmetNemeni(): void
    {
        $this->git('switch', '--quiet', '-c', '1274-eshop');
        $this->commit('Feature');
        $puvodni = $this->hlava();
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite', 'main');

        $this->git('cherry-pick', '-x', $puvodni);

        self::assertSame('1274 Feature', $this->predmet());
    }

    /**
     * Commit, který prefix nemá, se při cherry-picku prefixuje jako každý jiný.
     */
    public function testCherryPickCommituBezPrefixuHoDoplni(): void
    {
        $this->git('switch', '--quiet', '-c', 'bez-cisla');
        $this->commit('Feature bez prefixu');
        $puvodni = $this->hlava();
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite', 'main');

        $this->git('cherry-pick', $puvodni);

        self::assertSame('GH-1090 Feature bez prefixu', $this->predmet());
    }

    /**
     * Rebase předmět nikdy nezdvojoval (ověřeno i pro starý hook), takže to je jen pojistka,
     * aby se to nezačalo dít, až se hook příště bude měnit.
     */
    public function testRebasePredmetyNemeni(): void
    {
        $this->git('switch', '--quiet', '-c', '1274-eshop');
        $this->commit('Feature');
        $this->git('switch', '--quiet', '-c', 'gh-1000-vetev');
        $this->git('switch', '--quiet', 'main');
        $this->commit('Zmena na main');
        $this->git('switch', '--quiet', 'gh-1000-vetev');

        $this->git('rebase', '--quiet', 'main');

        self::assertSame(['1274 Feature'], $this->predmety('main..HEAD'));
    }

    /**
     * Revert na vlastní větvi nese v předmětu úkol už od vracené změny.
     */
    public function testRevertSVlastnimUkolemHoNezdvoji(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite');
        $this->commit('Upgrade vite');

        $this->git('revert', '--no-edit', 'HEAD');

        self::assertSame('GH-1090 Upgrade vite', $this->predmetVraceneho());
        self::assertSame('Revert "GH-1090 Upgrade vite"', $this->predmet());
    }

    /**
     * Revert vrácené změny vrací změnu zpátky pod názvem `Reapply "…"` a nese úkol stejně.
     */
    public function testReapplyPoRevertuUkolNezdvoji(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite');
        $this->commit('Upgrade vite');
        $this->git('revert', '--no-edit', 'HEAD');

        $this->git('revert', '--no-edit', 'HEAD');

        self::assertSame('Reapply "GH-1090 Upgrade vite"', $this->predmet());
    }

    public function testRevertCommituSJinymUkolemDostanePrefixVetve(): void
    {
        $this->git('switch', '--quiet', '-c', '1274-eshop');
        $this->commit('Feature');
        $puvodni = $this->hlava();
        $this->git('switch', '--quiet', '-c', 'gh-1090-vite', 'main');
        $this->git('merge', '--quiet', '--no-ff', '--no-edit', $puvodni);

        $this->git('revert', '--no-edit', '-m', '1', 'HEAD');

        self::assertStringStartsWith('GH-1090 Revert "', $this->predmet());
    }

    /**
     * `squash!` a `amend!` musí zůstat na začátku, jinak je `rebase --autosquash` nenajde.
     */
    public function testSquashAAmendSePrefixemNeopatri(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-3000-squash');
        $this->commit('Puvodni');
        $cil = $this->hlava();

        $this->commit('Oprava', '--squash=' . $cil);
        self::assertSame('squash! GH-3000 Puvodni', $this->predmet());

        $this->commit('x', '--fixup=amend:' . $cil);
        self::assertStringStartsWith('amend! GH-3000 Puvodni', $this->predmet());

        $this->commit('Dalsi', '--fixup=' . $cil);
        self::assertSame('fixup! GH-3000 Puvodni', $this->predmet());
    }

    public function testAutosquashSloucOpravyDoPuvodnihoCommitu(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-3000-squash');
        $this->commit('Puvodni');
        $cil = $this->hlava();
        $this->commit('Druhy');
        $this->commit('Oprava A', '--fixup=' . $cil);
        $this->commit('Oprava B', '--squash=' . $cil);

        $this->git('-c', 'sequence.editor=true', '-c', 'core.editor=true', 'rebase', '--quiet', '--interactive', '--autosquash', 'main');

        self::assertSame(['GH-3000 Druhy', 'GH-3000 Puvodni'], $this->predmety('main..HEAD'));
    }

    /**
     * `echo` v dash vykládá zpětná lomítka, takže se zpráva s `\n` nebo `\t` rozbila.
     */
    public function testZpetnaLomitkaVeZpraveZustanou(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-4000-lomitka');

        $this->commit('Oprava \\n v C:\\temp a \\\\ konec');

        self::assertSame('GH-4000 Oprava \\n v C:\\temp a \\\\ konec', $this->predmet());
    }

    public function testViceradkovaZpravaZachovaTelo(): void
    {
        $this->git('switch', '--quiet', '-c', 'gh-4000-lomitka');

        $this->commit('Predmet', '-m', 'Telo s \\t lomitkem');

        self::assertSame("GH-4000 Predmet\n\nTelo s \\t lomitkem", trim($this->git('log', '-1', '--format=%B')));
    }

    private function commit(string $zprava, string ...$dalsiArgumenty): void
    {
        $this->zmena();
        $volby = array_filter($dalsiArgumenty, static fn (string $volba): bool => str_starts_with($volba, '--'));
        $texty = array_values(array_diff($dalsiArgumenty, $volby));
        $argumenty = ['commit', '--quiet', ...$volby];
        // `--squash` a `--fixup` si předmět berou z cíle, takže jim zpráva nepatří.
        $mamePredmetZCile = array_filter($volby, static fn (string $volba): bool => str_starts_with($volba, '--squash=') || str_starts_with($volba, '--fixup='));
        if ($mamePredmetZCile === []) {
            $argumenty[] = '-m';
            $argumenty[] = $zprava;
        }
        foreach ($texty as $text) {
            if ($text === '-m') {
                continue;
            }
            $argumenty[] = '-m';
            $argumenty[] = $text;
        }
        $this->git(...$argumenty);
    }

    private function commitBezHooku(string $zprava): void
    {
        $this->zmena();
        $this->git('-c', 'core.hooksPath=/dev/null', 'commit', '--quiet', '-m', $zprava);
    }

    private function zmena(): void
    {
        ++$this->citac;
        file_put_contents($this->repozitar . '/soubor' . $this->citac, (string) $this->citac);
        $this->git('add', 'soubor' . $this->citac);
    }

    private function predmet(): string
    {
        return trim($this->git('log', '-1', '--format=%s'));
    }

    private function predmetVraceneho(): string
    {
        return trim($this->git('log', '-1', '--format=%s', 'HEAD~1'));
    }

    /**
     * @return list<string>
     */
    private function predmety(string $rozsah): array
    {
        return array_values(array_filter(explode("\n", trim($this->git('log', '--format=%s', $rozsah)))));
    }

    private function hlava(): string
    {
        return trim($this->git('rev-parse', 'HEAD'));
    }

    private function git(string ...$argumenty): string
    {
        $proces = proc_open(['git', ...$argumenty], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $roury, $this->repozitar, [
            'GIT_CONFIG_GLOBAL'       => '/dev/null',
            'GIT_CONFIG_NOSYSTEM'     => '1',
            'GIT_EDITOR'              => 'true',
            'GIT_CEILING_DIRECTORIES' => dirname((string) realpath($this->repozitar)),
            'PATH'                    => (string) getenv('PATH'),
            'HOME'                    => $this->repozitar,
        ]);
        self::assertIsResource($proces);
        $vystup = (string) stream_get_contents($roury[1]);
        $chyby = (string) stream_get_contents($roury[2]);
        $kod = proc_close($proces);
        self::assertSame(0, $kod, 'git ' . implode(' ', $argumenty) . ': ' . $chyby . $vystup);

        return $vystup;
    }
}
