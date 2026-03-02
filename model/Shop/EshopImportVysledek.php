<?php

declare(strict_types=1);

namespace Gamecon\Shop;

class EshopImportVysledek
{
    public function __construct(
        public readonly int $pocetNovych,
        public readonly int $pocetZmenenych,
        public readonly int $pocetVyrazenych,
    ) {}
}
