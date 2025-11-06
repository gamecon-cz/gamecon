<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Command\Proxy;

use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * @deprecated
 */
trait OrmProxyCommand
{
    public function __construct(
        private readonly EntityManagerProvider|null $entityManagerProvider = null,
    ) {
        parent::__construct($entityManagerProvider);

        Deprecation::trigger(
            'doctrine/doctrine-bundle',
            'https://github.com/doctrine/DoctrineBundle/pull/1581',
            'Class "%s" is deprecated. Use "%s" instead.',
            self::class,
            parent::class,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->entityManagerProvider) {
            /* @phpstan-ignore argument.type (ORM < 3 specific) */
            DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        }

        return parent::execute($input, $output);
    }
}
