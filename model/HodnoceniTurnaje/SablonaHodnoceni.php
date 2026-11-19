<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Škála, kategorie a kritéria hodnocení jednoho ročníku. Uložená šablona má id i u kategorií a kritérií. Šablona
 * z editoru (@see FormularKriterii) a ze souboru (@see ParserSablonyHodnoceni) je má jen u těch, které už jsou uložené.
 */
final readonly class SablonaHodnoceni
{
    /**
     * @param list<int>                $skala     hodnoty v pořadí, ve kterém je vypravěč uvidí v nabídce
     * @param list<KategorieHodnoceni> $kategorie
     */
    public function __construct(
        public ?int $id,
        public array $skala,
        public array $kategorie,
        public ?\DateTimeImmutable $uzavreno = null,
    ) {
    }

    public function isUzavrena(): bool
    {
        return $this->uzavreno !== null;
    }

    /**
     * Kritéria, která už mají id, podle id.
     *
     * @return array<int, KriteriumHodnoceni>
     */
    public function getKriteriaPodleId(): array
    {
        $kriteria = [];
        foreach ($this->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                if ($kriterium->id !== null) {
                    $kriteria[$kriterium->id] = $kriterium;
                }
            }
        }

        return $kriteria;
    }

    public function isVeSkale(int $hodnota): bool
    {
        return in_array($hodnota, $this->skala, true);
    }

    /**
     * Textový zápis (`Škála:`, `# `, `## `), který přečte @see ParserSablonyHodnoceni::rozeber().
     */
    public function getText(): string
    {
        $radky = ['Škála: ' . implode(' ', $this->skala)];
        foreach ($this->kategorie as $kategorie) {
            $radky[] = '# ' . $kategorie->nazev;
            foreach ($kategorie->kriteria as $kriterium) {
                $radky[] = '## ' . $kriterium->nazev;
            }
        }

        return implode("\n", $radky);
    }

    /**
     * Řádky tabulky pod hlavičkou @see ParserSablonyHodnoceni::HLAVICKA_TABULKY, které přečte
     * @see ParserSablonyHodnoceni::rozeberTabulku(): škála, pak kritérium na řádek s kategorií v každém řádku (tabulka
     * jde seřadit a filtrovat).
     *
     * @return list<list<string>>
     */
    public function getRadkyTabulky(): array
    {
        $radky = [[ParserSablonyHodnoceni::SKALA_V_TABULCE, implode(' ', $this->skala)]];
        foreach ($this->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $radky[] = [$kategorie->nazev, $kriterium->nazev];
            }
        }

        return $radky;
    }
}
