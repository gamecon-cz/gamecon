<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

// todo(tym): místo toho by měla být normální db entita AktivitaTým
class TymVSeznamu
{
    public function __construct(
        public readonly int                  $kod,
        public readonly ?string              $nazev,
        public readonly int                  $pocetClenu,
        public readonly ?int                 $limit,
        public readonly bool                 $verejny,
        public readonly int                  $idKapitana,
        public readonly ?\DateTimeImmutable  $zalozen,
    ) {
    }
}
