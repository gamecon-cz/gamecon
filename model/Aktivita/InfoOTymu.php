<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

class InfoOTymu
{
    public function __construct(
        public readonly int  $pocetClenu,
        public readonly ?int $limit,
    ) {
    }
}
