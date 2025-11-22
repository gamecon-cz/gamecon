<?php

/*
 * This file is part of the zenstruck/foundry package.
 *
 * (c) Kevin Bond <kevinbond@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Zenstruck\Foundry\InMemory\InMemoryFactoryRegistry;
use Zenstruck\Foundry\InMemory\InMemoryRepositoryRegistry;

return static function(ContainerConfigurator $container): void {
    $container->services()
        ->set('.zenstruck_foundry.in_memory.factory_registry', InMemoryFactoryRegistry::class)
        ->decorate('.zenstruck_foundry.factory_registry')
        ->arg('$decorated', service('.inner'));

    $container->services()
        ->set('.zenstruck_foundry.in_memory.repository_registry', InMemoryRepositoryRegistry::class)
        ->arg('$inMemoryRepositories', abstract_arg('inMemoryRepositories'))
    ;
};
