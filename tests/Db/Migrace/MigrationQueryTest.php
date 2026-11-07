<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db\Migrace;

use Godric\DbMigrations\Migration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A migration that reads before it writes — typically a guard counting rows that would break —
 * must get the rows back however the query starts, or the guard reads nothing and lets it through.
 */
class MigrationQueryTest extends KernelTestCase
{
    private Migration $migrace;

    private \PDO $connection;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->connection = dbConnectTemporary();
        $this->migrace = new Migration('/neexistujici-migrace.php', 'test', $this->connection);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dotazyVracejiciRadky(): array
    {
        return [
            'SELECT'              => ['SELECT 1 AS a UNION SELECT 2'],
            'CTE'                 => ['WITH x AS (SELECT 1 AS a UNION SELECT 2) SELECT a FROM x'],
            'komentář na začátku' => ["-- dva řádky\nSELECT 1 AS a UNION SELECT 2"],
        ];
    }

    /**
     * @dataProvider dotazyVracejiciRadky
     */
    public function testReadReturnsItsRows(string $dotaz): void
    {
        $this->assertEquals([1, 2], $this->migrace->q($dotaz)->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testScriptRunsEveryStatement(): void
    {
        $this->migrace->q(<<<SQL
CREATE TEMPORARY TABLE tmp_migration_query_test (id INT PRIMARY KEY);
INSERT INTO tmp_migration_query_test (id) VALUES (1);
INSERT INTO tmp_migration_query_test (id) VALUES (2);
SQL);

        $this->assertSame(2, (int) $this->migrace->q('SELECT COUNT(*) FROM tmp_migration_query_test')->fetchColumn());
    }

    /**
     * The database stops a script at its first error, so a silent failure leaves the rest
     * unapplied while the migration is recorded as done.
     */
    public function testLaterStatementFailureIsReported(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('tmp_neexistujici_tabulka');

        $this->migrace->q(<<<SQL
CREATE TEMPORARY TABLE tmp_migration_query_test (id INT PRIMARY KEY);
INSERT INTO tmp_neexistujici_tabulka (id) VALUES (1);
INSERT INTO tmp_migration_query_test (id) VALUES (2);
SQL);
    }

    public function testFailingSqlMigrationNamesItsFile(): void
    {
        $soubor = LOGY . '/migrace-s-chybou-' . uniqid('', false) . '.sql';
        file_put_contents($soubor, <<<SQL
CREATE TEMPORARY TABLE tmp_migration_query_test (id INT PRIMARY KEY);
INSERT INTO tmp_neexistujici_tabulka (id) VALUES (1);
SQL);

        try {
            (new Migration($soubor, 'test', $this->connection))->apply();
            $this->fail('Migrace s chybou ve druhém příkazu musí selhat');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(basename($soubor), $exception->getMessage());
        } finally {
            unlink($soubor);
        }
    }
}
