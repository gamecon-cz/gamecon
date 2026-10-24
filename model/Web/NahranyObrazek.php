<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubor, který prošel kontrolou @see NahravaniObrazku::overNahranySoubor() a ještě neleží na svém místě.
 * Typ a přípona jsou zjištěné z obsahu souboru, ne z názvu, který poslal prohlížeč. Rozměry a velikost jsou
 * nahraného souboru, `mime` a `pripona` toho, co se uloží.
 */
final readonly class NahranyObrazek
{
    /**
     * @param string|null $zdrojovyMime formát nahraného souboru, když se při uložení zmenší nebo převede
     *                                  (@see OptimalizaceObrazku); null, když se uloží tak, jak je
     */
    public function __construct(
        public string $cesta,
        public string $puvodniNazev,
        public string $mime,
        public string $pripona,
        public ?int $sirka,
        public ?int $vyska,
        public int $velikostBajtu,
        public bool $zHttpUploadu,
        public ?string $zdrojovyMime = null,
    ) {
    }

    public function sFormatem(string $mime, string $pripona, ?string $zdrojovyMime): self
    {
        return new self(
            $this->cesta,
            $this->puvodniNazev,
            $mime,
            $pripona,
            $this->sirka,
            $this->vyska,
            $this->velikostBajtu,
            $this->zHttpUploadu,
            $zdrojovyMime,
        );
    }

    public function nahranyMime(): string
    {
        return $this->zdrojovyMime ?? $this->mime;
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
