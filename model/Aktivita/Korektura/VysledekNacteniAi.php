<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * What a look at the AI batches brought: the answers of the ended ones, and what went wrong with the others.
 */
readonly class VysledekNacteniAi
{
    /**
     * @param array<string> $chyby of the batches whose answers could not be read, or that the API lost
     */
    public function __construct(
        // null when no batch has ended
        public ?VysledekImportu $vysledekImportu,
        public array $chyby,
    ) {
    }
}
