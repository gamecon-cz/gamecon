<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * Texts of one activity (a family of instances) sent out for proofreading, with the AI's proposal.
 * A proposed text is null where the AI changed nothing.
 */
readonly class KorekturaTextu
{
    public function __construct(
        public int $id,
        public int $idAktivity,
        public int $balik,
        public StavKorekturyEnum $stav,
        public TextyAktivity $puvodni,
        public ?string $navrhNazev,
        public ?string $navrhPopisKratky,
        public ?string $navrhPopis,
        public ?string $zmeny,
    ) {
    }

    /**
     * The proposal with the unchanged texts taken from the original.
     */
    public function navrh(): TextyAktivity
    {
        return new TextyAktivity(
            $this->navrhNazev ?? $this->puvodni->nazev,
            $this->navrhPopisKratky ?? $this->puvodni->popisKratky,
            $this->navrhPopis ?? $this->puvodni->popis,
        );
    }

    public function maNavrzeneZmeny(): bool
    {
        return $this->navrhNazev !== null || $this->navrhPopisKratky !== null || $this->navrhPopis !== null;
    }
}
