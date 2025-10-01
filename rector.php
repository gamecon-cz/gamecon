<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
                   ->withPreparedSets(
                       deadCode: true,
                       codeQuality: true,
                       codingStyle: true,
                       typeDeclarations: true,
                   )
                   ->withAttributesSets()
                   ->withPhpSets()
                   ->withImportNames(importShortClasses: false, removeUnusedImports: true);
