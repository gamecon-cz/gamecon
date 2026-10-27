<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

readonly class VysledekImportu
{
    /**
     * @param array<string> $varovani
     */
    public function __construct(
        public int $pocetNavrhu,
        public int $pocetBezZmen,
        public array $varovani,
    ) {
    }
}
