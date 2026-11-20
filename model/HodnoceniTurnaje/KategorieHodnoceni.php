<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Kategorie hodnocení, výsledky sčítají body skupiny po kategoriích.
 */
final readonly class KategorieHodnoceni
{
    /**
     * @param list<KriteriumHodnoceni> $kriteria
     */
    public function __construct(
        public ?int $id,
        public string $nazev,
        public array $kriteria,
    ) {
    }
}
