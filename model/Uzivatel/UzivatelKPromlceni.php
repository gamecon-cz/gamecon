<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * DTO objekt reprezentující uživatele určeného k promlčení zůstatku
 * Obsahuje standardní Uzivatel objekt plus další metadata související s promlčením
 */
readonly class UzivatelKPromlceni
{
    public function __construct(
        public \Uzivatel $uzivatel,
        public string    $prihlaseniNaRocniky,
        public ?int      $rokPosledniPlatby,
        public ?int      $mesicPosledniPlatby,
        public ?int      $denPosledniPlatby,
    ) {
    }
}
