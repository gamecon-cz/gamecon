<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

readonly class UlozenaEmailovaSablona
{
    public function __construct(
        public string $kod,
        public string $predmet,
        public string $text,
        public ?int $zmenil = null,
        public ?\DateTimeInterface $zmenenoKdy = null,
    ) {
    }

    public function jeVyplnena(): bool
    {
        return !RozkladSymbolu::jePrazdny($this->predmet) && !RozkladSymbolu::jePrazdny($this->text);
    }
}
