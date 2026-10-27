<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * One activity as the AI answered it. A text is null where the AI left its tag out, i.e. changed nothing.
 */
readonly class NavrhZOdpovedi
{
    public function __construct(
        public int $idAktivity,
        public ?string $nazev,
        public ?string $popisKratky,
        public ?string $popis,
        public ?string $zmeny,
    ) {
    }
}
