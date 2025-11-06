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

use Zenstruck\Foundry\Command\StubCommand;

return static function(ContainerConfigurator $container): void {
    $container->services()
        ->set('.zenstruck_foundry.make_factory_command', StubCommand::class)
            ->args([
                "To run \"make:factory\" you need the \"MakerBundle\" which is currently not installed.\n\nTry running \"composer require symfony/maker-bundle --dev\".",
            ])
            ->tag('console.command', [
                'command' => '|make:factory',
                'description' => 'Creates a Foundry object factory',
            ])
        ->set('.zenstruck_foundry.make_story_command', StubCommand::class)
            ->args([
                "To run \"make:story\" you need the \"MakerBundle\" which is currently not installed.\n\nTry running \"composer require symfony/maker-bundle --dev\".",
            ])
            ->tag('console.command', [
                'command' => '|make:story',
                'description' => 'Creates a Foundry story',
            ])
    ;
};
