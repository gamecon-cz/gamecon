<?php

declare(strict_types=1);

namespace Gamecon\Web;

final readonly class OdkazVTextu
{
    /**
     * @param string $cesta vůči adresáři obsahu
     * @param bool $jeVlozeny obrázek zobrazený v textu, ne odkaz na soubor
     */
    public function __construct(
        public string $cesta,
        public bool $jeVlozeny,
    ) {
    }
}
