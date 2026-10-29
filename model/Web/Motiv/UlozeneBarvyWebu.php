<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

readonly class UlozeneBarvyWebu
{
    public function __construct(
        public BarvyWebu $barvy,
        public ?int $zmenil,
        public ?\DateTimeImmutable $zmenenoKdy,
    ) {
    }
}
