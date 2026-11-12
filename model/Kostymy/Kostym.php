<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * Kostým z katalogu, jak ho čte @see KatalogKostymu
 */
final class Kostym
{
    /**
     * @param list<FotkaKostymu> $fotky v pořadí, první je hlavní
     */
    public function __construct(
        private readonly int $id,
        private readonly string $nazev,
        private readonly string $popis,
        private readonly VelikostKostymuEnum $velikost,
        private readonly string $miry,
        private readonly StrihKostymuEnum $strih,
        private readonly string $kategorie,
        private readonly StavKostymuEnum $stav,
        private readonly string $interniPoznamka,
        private readonly int $poradi,
        private readonly array $fotky,
    ) {
    }

    /**
     * @param array<string, mixed> $radek
     * @param list<FotkaKostymu>   $fotky
     */
    public static function zRadku(array $radek, array $fotky): self
    {
        return new self(
            id: (int) $radek['id'],
            nazev: (string) $radek['nazev'],
            popis: (string) $radek['popis'],
            velikost: VelikostKostymuEnum::from((string) $radek['velikost']),
            miry: (string) $radek['miry'],
            strih: StrihKostymuEnum::from((string) $radek['strih']),
            kategorie: (string) $radek['kategorie'],
            stav: StavKostymuEnum::from((string) $radek['stav']),
            interniPoznamka: (string) $radek['interni_poznamka'],
            poradi: (int) $radek['poradi'],
            fotky: $fotky,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function getPopis(): string
    {
        return $this->popis;
    }

    public function getVelikost(): VelikostKostymuEnum
    {
        return $this->velikost;
    }

    public function getMiry(): string
    {
        return $this->miry;
    }

    public function getStrih(): StrihKostymuEnum
    {
        return $this->strih;
    }

    public function getKategorie(): string
    {
        return $this->kategorie;
    }

    public function getStav(): StavKostymuEnum
    {
        return $this->stav;
    }

    public function getInterniPoznamka(): string
    {
        return $this->interniPoznamka;
    }

    public function getPoradi(): int
    {
        return $this->poradi;
    }

    /**
     * @return list<FotkaKostymu>
     */
    public function getFotky(): array
    {
        return $this->fotky;
    }

    public function getHlavniFotka(): ?FotkaKostymu
    {
        return $this->fotky[0] ?? null;
    }
}
