<?php

declare(strict_types=1);

namespace App\Command;

use Gamecon\SystemoveNastaveni\SqlMigrace;
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

        // Load legacy bootstrap — definuje konstanty potřebné pro SqlMigrace
        require_once __DIR__ . '/../../../nastaveni/zavadec-zaklad.php';

        // Delegujeme na Gamecon\SystemoveNastaveni\SqlMigrace, aby byla jediná
        // cesta pro spouštění migrací. SqlMigrace se po úspěchu také postará
        // o označení JSON programu jako dirty (migrace typicky mění data
        // zobrazovaná v programu a žádný listener to nezachytí).
        try {
            SqlMigrace::vytvorZGlobals()->migruj(zalohuj: false);
            $io->success('All migrations have been applied successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
