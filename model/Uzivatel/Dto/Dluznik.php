<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Dto;

use Uzivatel;

/**
 * DTO pro uživatele s dluhem
 */
readonly class Dluznik
{
    public function __construct(
        public Uzivatel $uzivatel,
        public float    $dluh, // Výše dluhu (jako kladné číslo)
    ) {
    }
}
