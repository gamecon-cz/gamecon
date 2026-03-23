<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

class VerejnyTym
{
    public function __construct(
        public readonly int     $kod,
        public readonly ?string $nazev,
        public readonly int     $pocetClenu,
        public readonly ?int    $limit,
    ) {
    }
}
