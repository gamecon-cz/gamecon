<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\RemoveNextrasMigrationsCommandsPass;
use App\Doctrine\SharedConnection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function shutdown(): void
    {
        SharedConnection::keepOpenWhile(parent::shutdown(...));
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveNextrasMigrationsCommandsPass());
    }
}
