<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

use Gamecon\Kanaly\EmailovaSablona\RozkladSymbolu;

readonly class VlastniZneniUpominky
{
    public function __construct(
        public string $predmet,
        public string $text,
        public ?int $zmenil = null,
        public ?\DateTimeInterface $zmenenoKdy = null,
    ) {
    }

    public function jeVyplnene(): bool
    {
        return !RozkladSymbolu::jePrazdny($this->predmet) && !RozkladSymbolu::jePrazdny($this->text);
    }
}
