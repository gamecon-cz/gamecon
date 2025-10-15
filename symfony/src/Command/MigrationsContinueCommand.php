<?php

declare(strict_types=1);

namespace App\Command;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrations:continue',
    description: 'Updates database schema by running all new migrations (legacy + Doctrine)',
)]
class MigrationsContinueCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Running Database Migrations');

        // Load legacy bootstrap
        require_once __DIR__ . '/../../../nastaveni/zavadec-zaklad.php';

        // Create database connection for migrations (from db-migrace.php)
        $connection = dbConnectTemporary(selectDb: false);

        // Ensure database exists and is selected
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

        try {
            $dbMigrations->run(silent: true);
            $io->success('All migrations have been applied successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
