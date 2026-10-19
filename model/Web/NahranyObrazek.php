<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubor, který prošel kontrolou @see NahravaniObrazku::overNahranySoubor() a ještě neleží na svém místě.
 * Typ a přípona jsou zjištěné z obsahu souboru, ne z názvu, který poslal prohlížeč.
 */
final readonly class NahranyObrazek
{
    public function __construct(
        public string $cesta,
        public string $puvodniNazev,
        public string $mime,
        public string $pripona,
        public ?int $sirka,
        public ?int $vyska,
        public int $velikostBajtu,
        public bool $zHttpUploadu,
    ) {
    }

    /**
     * Název, pod kterým soubor poslal prohlížeč, bez cesty a přípony. Hodí se jako výchozí název,
     * před uložením ho ale vždy proveď přes @see NahravaniObrazku::bezpecnyNazev()
     */
    public function puvodniNazevBezPripony(): string
    {
        $nazev = basename(str_replace('\\', '/', $this->puvodniNazev));

        return (string) preg_replace('~\.[^.]*$~', '', $nazev);
    }
}
