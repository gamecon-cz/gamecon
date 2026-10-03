<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

use App\Enum\ProductTagCode;

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
        /** A shop item's category; null for activities, payments and other non-shop rows. */
        public ?ProductTagCode $kategorie = null,
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
