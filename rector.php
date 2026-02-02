<?php

use Rector\Config\RectorConfig;
use Gamecon\Tests\Rector\Rules\ReorderAttributeArgumentsRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;

return RectorConfig::configure()
                   ->withPreparedSets(
                       deadCode: true,
                       codeQuality: true,
                       codingStyle: true,
                       typeDeclarations: true,
                   )
                   ->withAttributesSets()
                   ->withPhpSets()
                   ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRules([
        ReorderAttributeArgumentsRector::class,
        AddTypeToConstRector::class,
    ])
    ->withPaths([
        __DIR__ . '/symfony',
    ])
    ->withSkipPath(__DIR__ . '/symfony/var')
    ->withSymfonyContainerXml(__DIR__ . '/symfony/var/cache/dev/App_KernelDevDebugContainer.xml');

