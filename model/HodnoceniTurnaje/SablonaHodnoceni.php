<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Škála, kategorie a kritéria hodnocení jednoho ročníku. Rozebraný soubor (@see ParserSablonyHodnoceni) ještě nemá id,
 * uložená šablona ho má i u kategorií a kritérií.
 */
final readonly class SablonaHodnoceni
{
    /**
     * @param list<int>                $skala     hodnoty v pořadí ze souboru
     * @param list<KategorieHodnoceni> $kategorie
     */
    public function __construct(
        public ?int $id,
        public array $skala,
        public array $kategorie,
        public string $zdroj,
        public ?\DateTimeImmutable $uzavreno = null,
    ) {
    }

    public function isUzavrena(): bool
    {
        return $this->uzavreno !== null;
    }

    /**
     * Škála a počty kritérií v kategoriích. Šablony se stejným tvarem se liší nanejvýš v názvech, takže nové nahrání
     * hodnocením nic nevezme.
     *
     * @return array{skala: list<int>, kriteria: list<int>}
     */
    public function getTvar(): array
    {
        return [
            'skala'    => $this->skala,
            'kriteria' => array_map(
                static fn (KategorieHodnoceni $kategorie): int => count($kategorie->kriteria),
                $this->kategorie,
            ),
        ];
    }

    public function isStejnyTvar(self $jina): bool
    {
        return $this->getTvar() === $jina->getTvar();
    }

    public function getPocetKriterii(): int
    {
        return array_sum($this->getTvar()['kriteria']);
    }

    /**
     * Kritéria uložené šablony podle id.
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
}
