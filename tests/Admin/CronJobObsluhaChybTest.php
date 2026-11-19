<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pád jobu spuštěného přes ?job= musí přijít mailem (CronErrorNotifier), ne jen do logu cronu.
 *
 * Joby za prvním blokem try v admin/cron/_cron_job.php (mimo jiné upomínky dlužníkům a promlčení)
 * dřív padaly jen do souboru logu, který nikdo nečte. Test proto hlídá, že každý job je v try,
 * jehož catch volá CronErrorNotifier, i ten, který se přidá příště.
 */
class CronJobObsluhaChybTest extends TestCase
{
    private const string CRON_JOB = __DIR__ . '/../../admin/cron/_cron_job.php';

    public static function provideJoby(): iterable
    {
        $ast = self::dejAst();
        $finder = new NodeFinder();

        $obslouzene = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\TryCatch::class) as $tryCatch) {
            if (! self::catchOhlasujeChybu($tryCatch)) {
                continue;
            }
            // jen tělo try, require v catch nebo finally by chybu nezachytil
            foreach ($finder->findInstanceOf($tryCatch->stmts, Node\Expr\Include_::class) as $include) {
                $obslouzene[spl_object_id($include)] = true;
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Expr\Include_::class) as $include) {
            $soubor = self::dejSouborJobu($include);
            if ($soubor === null) {
                continue;
            }
            yield $soubor => [$soubor, isset($obslouzene[spl_object_id($include)])];
        }
    }

    #[DataProvider('provideJoby')]
    public function testPadJobuPrijdeMailem(string $soubor, bool $vTryHlasicimChybu): void
    {
        self::assertTrue(
            $vTryHlasicimChybu,
            "Job {$soubor} musí být v try, jehož catch předá chybu CronErrorNotifier, jinak jeho pád skončí jen v logu cronu",
        );
    }

    public function testSkriptSpoustiUpominkyIPromlceni(): void
    {
        $joby = array_keys(iterator_to_array(self::provideJoby()));

        // Kdyby se cesty v require přepsaly tak, že je test nepozná, prošel by naprázdno
        foreach ([
            'mail_dluzniku_tyden.php',
            'mail_dluzniku_mesic.php',
            'mail_promlceni_varovani_mesic.php',
            'mail_promlceni_varovani_tyden.php',
            'promlceni_automaticke.php',
        ] as $ocekavanyJob) {
            self::assertContains($ocekavanyJob, $joby);
        }
    }

    /**
     * @return Node[]
     */
    private static function dejAst(): array
    {
        $kod = file_get_contents(self::CRON_JOB);
        self::assertIsString($kod);

        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($kod);
        self::assertNotNull($ast);

        return $ast;
    }

    private static function catchOhlasujeChybu(Node\Stmt\TryCatch $tryCatch): bool
    {
        $finder = new NodeFinder();
        foreach ($tryCatch->catches as $catch) {
            $ohlaseni = $finder->findFirst(
                $catch->stmts,
                static fn (Node $node) => $node instanceof Node\Expr\New_
                    && $node->class instanceof Node\Name
                    && $node->class->getLast() === 'CronErrorNotifier',
            );
            if ($ohlaseni !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Název souboru jobu z `require __DIR__ . '/jobs/xyz.php'`, jinak null.
     */
    private static function dejSouborJobu(Node\Expr\Include_ $include): ?string
    {
        $cesta = $include->expr;
        if ($cesta instanceof Node\Expr\BinaryOp\Concat) {
            $cesta = $cesta->right;
        }
        if (! $cesta instanceof Node\Scalar\String_ || ! str_starts_with($cesta->value, '/jobs/')) {
            return null;
        }

        return substr($cesta->value, strlen('/jobs/'));
    }
}
