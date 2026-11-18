<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Kritérium v kategorii, vypravěč u něj vybere hodnotu ze škály. Kritérium z rozebraného souboru ještě nemá id.
 */
final readonly class KriteriumHodnoceni
{
    public function __construct(
        public ?int $id,
        public string $nazev,
    ) {
    }
}
