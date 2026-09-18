<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Volno na jedné noci jednoho typu pokoje.
 *
 * `remaining` už má odečtenou rezervaci pro organizátory a přičtené vlastní koupené noci,
 * takže je to číslo, které smí vidět zrovna tenhle zákazník — ne absolutní zbytek skladu.
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
