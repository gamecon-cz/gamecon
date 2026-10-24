<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

/**
 * Pro test, který zapisuje do skutečného stromu webu. Třída, která trait používá, musí mít pole `$kUklidu`
 * se soubory a adresáři ke smazání po testu.
 */
trait UklidNovychAdresaru
{
    /**
     * Uklidí i nadřazené adresáře, které test sám založil, a žádný, který tam byl už před ním.
     */
    private function uklidNoveAdresare(string $adresar): void
    {
        $nejvyssiNovy = null;
        while (! is_dir($adresar)) {
            $nejvyssiNovy = $adresar;
            $adresar = dirname($adresar);
        }
        if ($nejvyssiNovy !== null) {
            $this->kUklidu[] = $nejvyssiNovy;
        }
    }
}
