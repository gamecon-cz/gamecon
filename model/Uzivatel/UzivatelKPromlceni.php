<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * DTO objekt reprezentující uživatele určeného k promlčení zůstatku
 * Obsahuje standardní Uzivatel objekt plus další metadata související s promlčením
 */
class UzivatelKPromlceni
{
    public function __construct(
        public readonly \Uzivatel $uzivatel,
        public readonly string $prihlaseniNaRocniky,
        public readonly ?string $kladnyPohyb,
        public readonly ?int $rokPosledniPlatby,
        public readonly ?int $mesicPosledniPlatby,
        public readonly ?int $denPosledniPlatby,
    ) {
    }
}
