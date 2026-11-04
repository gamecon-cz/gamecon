<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubor poslaný po částech, který je celý a čeká na zpracování. Leží v mezisouboru, který po zpracování smaže
 * @see NahravaniPoCastech::smaz()
 */
final readonly class NahranyPoCastech
{
    public function __construct(
        public string $cesta,
        public string $puvodniNazev,
        public int $velikostBajtu,
    ) {
    }
}
