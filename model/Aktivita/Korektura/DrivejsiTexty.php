<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * Proofread texts of an activity with the same name from an earlier year, a pattern for the AI.
 */
readonly class DrivejsiTexty
{
    public function __construct(
        public int $rok,
        public TextyAktivity $texty,
    ) {
    }
}
