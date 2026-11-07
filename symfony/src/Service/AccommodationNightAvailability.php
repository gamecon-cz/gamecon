<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Volno na jedné noci jednoho typu pokoje.
 *
 * `remaining` už má odečtenou rezervaci pro organizátory, takže je to číslo, které smí vidět
 * zrovna tenhle zákazník — ne absolutní zbytek skladu. Vlastní koupená noc je v něm odečtená
 * jako každá jiná: je to zbytek pro ostatní.
 */
readonly class AccommodationNightAvailability
{
    public function __construct(
        public ?int $remaining,
        public bool $offered,
        public ?int $reservedForOrganizers,
    ) {
    }

    public function soldOut(): bool
    {
        return $this->remaining !== null && $this->remaining <= 0;
    }
}
