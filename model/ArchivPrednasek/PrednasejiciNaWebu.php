<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

final readonly class PrednasejiciNaWebu
{
    /**
     * @param string|null $urlMedailonku relative to the base of the web, null without a user (every user has one, a group or a company too)
     */
    public function __construct(
        public int $id,
        public string $jmeno,
        public ?string $urlMedailonku,
    ) {
    }
}
