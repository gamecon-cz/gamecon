<?php

declare(strict_types=1);

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:push:generate-vapid-keys',
    description: 'Generate a VAPID key pair for web push notifications (one pair per environment).',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function configure(): void
    {
        $this->setHelp(
            'Prints a new VAPID key pair as VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY. Nothing is stored: put the '
            . 'values into the secrets of the environment they are for (docs/push-notifikace.md). Every environment '
            . '(production, beta, previews) needs its own pair. Replacing the keys of an environment ends every '
            . 'subscription made with the old ones, users have to turn the notifications on again.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keys = VAPID::createVapidKeys();

        // Plain lines on stdout so the output can be pasted or redirected; the private key is never logged.
        $output->writeln('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $output->writeln('VAPID_PRIVATE_KEY=' . $keys['privateKey']);

        return Command::SUCCESS;
    }
}
