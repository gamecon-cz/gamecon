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
    name: 'migrations:reset',
    description: 'Resets database schema drop and recreate and by running all new migrations (legacy + Doctrine)',
)]
class MigrationsResetCommand extends Command
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        // Create a database connection for migrations (from db-migrace.php)
        $connection = dbConnectTemporary(selectDb: false);

        $io->title(sprintf('Recreating Database %s', DB_NAME));

        // Recreate database
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), null, $connection);
        dbQuery(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci', DB_NAME), null, $connection);
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
            $io->title(sprintf('Running Database Migrations On %s', DB_NAME));

            $dbMigrations->run(silent: true);
            $io->success('All migrations have been applied successfully');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $io->error('Migration failed: ' . $throwable->getMessage());
            $io->writeln($throwable->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
