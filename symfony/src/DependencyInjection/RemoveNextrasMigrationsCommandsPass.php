<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Nextras\Migrations\Bridges\SymfonyConsole\ContinueCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
    }
}
