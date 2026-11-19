<?php

declare(strict_types=1);

namespace Gamecon\Cron;

/**
 * Jeden termín automatického jobu, který hlídá HlidaniZmeskanychTerminu.
 */
final readonly class HlidanyTermin
{
    /**
     * @param string $klic jednoznačný v rámci ročníku, např. upominani-tyden-2026
     * @param string $nazev lidsky, do upozornění
     * @param string $job hodnota parametru ?job=, kterým se job spouští
     * @param string $coDal co má CFO nebo IT udělat, když termín propásl
     * @param \Closure(): ?\DateTimeInterface $probehloKdy kdy job v termínu doběhl, null když ne
     */
    public function __construct(
        public string       $klic,
        public string       $nazev,
        public OknoSpusteni $okno,
        public string       $job,
        public string       $coDal,
        public \Closure     $probehloKdy,
    ) {
    }
}
