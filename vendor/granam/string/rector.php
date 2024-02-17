<?php

declare(strict_types=1);

use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

return static function (\Rector\Config\RectorConfig $rectorConfig): void {    // get parameters

    // Define what rule sets will be applied
    $rectorConfig->sets([
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_91,
    ]);
};
