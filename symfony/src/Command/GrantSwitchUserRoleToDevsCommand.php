<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRoleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:grant-switch-user-role-to-devs',
    description: 'Grant the current-year "switch to user" role to all Dev-role users (run after restoring the production DB into a preview).',
)]
class GrantSwitchUserRoleToDevsCommand extends Command
{
    public function __construct(
        private readonly UserRoleRepository $userRoleRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'year',
                null,
                InputOption::VALUE_REQUIRED,
                'Ročník whose switch-user role to grant (default: current ROCNIK)',
            )
            ->setHelp(
                'Preview environments run on a freshly restored production DB. The '
                . '"switch to user" role is yearly and resets each ročník, so the '
                . 'restored DB never carries it. This command grants it to every '
                . 'Dev-role user so developers can impersonate any user on the '
                . 'preview. The deploy script runs it right after the DB restore.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $year = $this->resolveYear($input);

        try {
            $granted = $this->userRoleRepository->grantSwitchUserRoleToDevs($year);
            $io->success(sprintf(
                'Granted the switch-user role for year %d to %d Dev user(s).',
                $year,
                $granted,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $io->error('Granting the role failed: ' . $throwable->getMessage());
            $io->writeln($throwable->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function resolveYear(InputInterface $input): int
    {
        $yearOption = $input->getOption('year');
        if ($yearOption !== null) {
            return (int) $yearOption;
        }

        // Legacy bootstrap defines the current ročník as the ROCNIK constant.
        require_once __DIR__ . '/../../../nastaveni/zavadec-zaklad.php';

        return ROCNIK;
    }
}
