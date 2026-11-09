<?php

declare(strict_types=1);

namespace Gamecon\Web;

final readonly class PolozkaPrevoduObrazku
{
    /**
     * @param string $cesta vůči adresáři obsahu
     * @param string $podrobnosti například text chyby, když převod selhal
     */
    public function __construct(
        public string $cesta,
        public StavPrevoduObrazkuEnum $stav,
        public string $podrobnosti = '',
        public int $puvodniBajtu = 0,
        public int $novychBajtu = 0,
    ) {
    }

    public function getUsporaBajtu(): int
    {
        return $this->stav === StavPrevoduObrazkuEnum::PREVEDENO
            ? $this->puvodniBajtu - $this->novychBajtu
            : 0;
    }
}
