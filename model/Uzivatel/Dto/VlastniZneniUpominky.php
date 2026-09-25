<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

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
        return trim($this->predmet) !== '' && trim($this->text) !== '';
    }
}
