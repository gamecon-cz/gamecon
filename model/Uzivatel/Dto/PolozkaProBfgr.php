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
        public string $idVarianty,
    ) {
    }

    /**
     * Activities, payments and balances share the participant's finance items but carry no product code.
     */
    public function jeNakup(): bool
    {
        return $this->kodPredmetu !== '';
    }
}
