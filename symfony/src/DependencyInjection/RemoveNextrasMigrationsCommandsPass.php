<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Nextras\Migrations\Bridges\SymfonyConsole\ContinueCommand;
use Nextras\Migrations\Bridges\SymfonyConsole\ResetCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes Nextras migrations commands to use our custom implementations.
 */
class RemoveNextrasMigrationsCommandsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Remove the Nextras migrations:continue command to use our custom implementation
        if ($container->hasDefinition(ContinueCommand::class)) {
            $container->removeDefinition(ContinueCommand::class);
        }

        // Also try to remove by service ID if it's aliased
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->getClass() === ContinueCommand::class) {
                $container->removeDefinition($id);
            }
        }

        // Remove the Nextras migrations:reset command to use our custom implementation
        if ($container->hasDefinition(ResetCommand::class)) {
            $container->removeDefinition(ResetCommand::class);
        }

        // Also try to remove by service ID if it's aliased
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->getClass() === ResetCommand::class) {
                $container->removeDefinition($id);
            }
        }
    }
}
