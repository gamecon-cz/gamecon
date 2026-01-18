<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

readonly class PolozkaProBfgr
{
    public function __construct(
        public string $nazev,
        public string $pocet,
        public float $castka,
        public float $sleva,
        public int $typ,
        public string $kodPredmetu,
        public string $idPredmetu,
        public ?int $modelRok = null,
    ) {
    }
}
