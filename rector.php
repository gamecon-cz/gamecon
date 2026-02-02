<?php

use Rector\Config\RectorConfig;
use App\Rector\ReorderAttributeArgumentsRector;

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
    ])
    ->withPaths([
        __DIR__ . '/symfony',
    ])
    ->withSkipPath(__DIR__ . '/symfony/var')
    ->withSymfonyContainerXml(__DIR__ . '/symfony/var/cache/dev/App_KernelDevDebugContainer.xml');
