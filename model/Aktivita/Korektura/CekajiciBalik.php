<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * A package with texts still waiting for the AI's answer, some of them perhaps sent through the API.
 */
readonly class CekajiciBalik
{
    public function __construct(
        public int $balik,
        public int $pocetAktivit,
        public int $pocetUAi,
        // the earliest sending of the package's texts still waiting at the AI
        public ?\DateTimeImmutable $odeslanoAiKdy,
    ) {
    }

    public function maTextyKOdeslaniAi(): bool
    {
        return $this->pocetUAi < $this->pocetAktivit;
    }
}
