<?php

use Rector\Config\RectorConfig;
use App\Rector\ReorderAttributeArgumentsRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;

/* @link https://github.com/rectorphp/rector-symfony */
return static function (
    RectorConfig $rectorConfig,
): void {
    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');

    $rectorConfig->rule(ReorderAttributeArgumentsRector::class);
    $rectorConfig->rule(AddTypeToConstRector::class);
    $rectorConfig->paths([__DIR__ . '/symfony/src', __DIR__ . '/tests']);
};
