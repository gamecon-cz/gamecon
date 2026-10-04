<?php

declare(strict_types=1);

namespace App\Command;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrations:continue',
    description: 'Runs all new SQL migrations via the legacy runner and marks the JSON program cache dirty',
)]
class MigrationsContinueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_REQUIRED);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $io->title(sprintf('Running Database Migrations On %s', DB_NAME));

        // Create a database connection for migrations (from db-migrace.php)
        $connection = dbConnectTemporary(selectDb: false);

        // Ensure a database exists and is selected
        dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci', DB_NAME), null, $connection);
        dbQuery(sprintf('USE `%s`', DB_NAME), null, $connection);

        // Create migrations config
        $migrationsConfig = new DbMigrationsConfig(
            connection: $connection,
            migrationsDirectory: SQL_MIGRACE_DIR,
            doBackups: false,
            backupsDirectory: SQL_MIGRACE_DIR . '/zalohy',
            useWebGui: false,
        );

        // Run migrations silently
        $dbMigrations = new DbMigrations($migrationsConfig);

        $puvodniRezim = $this->zapniPrisnyRezim($connection);

        try {
            $dbMigrations->run(silent: true);
            $io->success('All migrations have been applied successfully');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $io->error('Migration failed: ' . $throwable->getMessage());
            $io->writeln($throwable->getTraceAsString());

            return Command::FAILURE;
        } finally {
            dbQuery('SET SESSION sql_mode = $0', [$puvodniRezim], $connection);
        }
    }

    /**
     * Bez striktního režimu je zúžení sloupce jen varování: `DECIMAL(10,2)` na `DECIMAL(4,2)`
     * uřízne 12345.67 na 99.99, migrace projde a data jsou pryč. Se `STRICT_ALL_TABLES` to
     * skončí chybou 1264 a hodnoty zůstanou.
     *
     * @return string původní režim, který volající musí vrátit zpátky
     */
    private function zapniPrisnyRezim(\PDO $connection): string
    {
        $puvodniRezim = (string) dbOneCol('SELECT @@SESSION.sql_mode', null, $connection);
        dbQuery('SET SESSION sql_mode = $0', [$puvodniRezim . ',STRICT_ALL_TABLES'], $connection);

        return $puvodniRezim;
    }
}
