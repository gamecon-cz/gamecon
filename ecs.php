<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/symfony/src',
        __DIR__ . '/symfony/config',
        __DIR__ . '/tests/Symfony',
    ])
    ->withSkip([
        __DIR__ . '/symfony/var',
    ])
    ->withPreparedSets(
        psr12: true,
        arrays: true,
        comments: true,
        docblocks: true,
        spaces: true,
        namespaces: true,
        controlStructures: true,
        strict: true,
        cleanCode: true,
    )->withPhpCsFixerSets(symfony: true);
