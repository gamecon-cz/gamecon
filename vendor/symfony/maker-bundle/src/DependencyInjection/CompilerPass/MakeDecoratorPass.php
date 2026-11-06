<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass;

use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Benjamin Georgeault <git@wedgesama.fr>
 */
class MakeDecoratorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('maker.decorator_helper')) {
            return;
        }

        $shortNameMap = [];
        $serviceClasses = [];
        foreach ($container->getServiceIds() as $id) {
            if (str_starts_with($id, '.')) {
                continue;
            }

            if (str_contains($id, '\\')) {
                $shortClass = Str::getShortClassName($id);
                $shortNameMap[$shortClass] ??= [];
                $shortNameMap[$shortClass][] = $id;
            }

            if (!$container->hasDefinition($id)) {
                continue;
            }

            if (
                (null === $class = $container->getDefinition($id)->getClass())
                || $class === $id
            ) {
                continue;
            }

            $shortClass = Str::getShortClassName($class);
            $shortNameMap[$shortClass] ??= [];
            $shortNameMap[$shortClass][] = $id;
            $serviceClasses[$id] = $class;
        }

        foreach ($shortNameMap as $shortClass => $ids) {
            $shortNameMap[$shortClass] = array_unique($ids);
        }

        $ids = $container->getServiceIds();
        $container->getDefinition('maker.decorator_helper')
            ->replaceArgument(0, $ids)
            ->replaceArgument(1, $serviceClasses)
            ->replaceArgument(2, $shortNameMap)
        ;
    }
}
