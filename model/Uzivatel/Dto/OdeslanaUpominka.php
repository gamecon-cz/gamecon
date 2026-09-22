<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

use Gamecon\Uzivatel\Enum\TypUpominky;

readonly class OdeslanaUpominka
{
    public function __construct(
        public TypUpominky $typUpominky,
        public int $dluh,
        public int $odeslal,
        public \DateTimeInterface $kdy,
    ) {
    }
}
