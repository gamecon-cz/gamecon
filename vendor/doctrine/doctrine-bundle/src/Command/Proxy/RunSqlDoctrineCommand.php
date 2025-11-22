<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\Deprecations\Deprecation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Execute a SQL query and output the results.
 *
 * @deprecated use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand instead
 */
class RunSqlDoctrineCommand extends RunSqlCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('doctrine:query:sql')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command executes the given SQL query and
outputs the results:

<info>php %command.full_name% "SELECT * FROM users"</info>
EOT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Deprecation::trigger(
            'doctrine/doctrine-bundle',
            'https://github.com/doctrine/DoctrineBundle/pull/1231',
            'The "%s" (doctrine:query:sql) is deprecated, use dbal:run-sql command instead.',
            self::class,
        );

        return parent::execute($input, $output);
    }
}
