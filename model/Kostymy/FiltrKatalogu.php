<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * Výběr z katalogu: velikost, střih, kategorie a případně jen kostýmy s danými ID (volné v čase aktivity).
 */
final class FiltrKatalogu
{
    /**
     * @param list<int>|null $jenIds null = bez omezení, [] = nic
     */
    public function __construct(
        public readonly ?VelikostKostymuEnum $velikost = null,
        public readonly ?StrihKostymuEnum $strih = null,
        public readonly ?string $kategorie = null,
        public readonly ?array $jenIds = null,
        public readonly bool $jenKDispozici = false,
    ) {
    }

    /**
     * Z parametrů adresy; neznámá hodnota se ignoruje, jako by filtr nebyl.
     *
     * @param array<string, mixed> $parametry
     */
    public static function zParametru(array $parametry): self
    {
        $kategorie = trim((string) ($parametry['kategorie'] ?? ''));

        return new self(
            velikost: VelikostKostymuEnum::tryFrom((string) ($parametry['velikost'] ?? '')),
            strih: StrihKostymuEnum::tryFrom((string) ($parametry['strih'] ?? '')),
            kategorie: $kategorie !== ''
                ? $kategorie
                : null,
        );
    }

    /**
     * @param list<int>|null $jenIds
     */
    public function sJenIds(?array $jenIds): self
    {
        return new self($this->velikost, $this->strih, $this->kategorie, $jenIds, $this->jenKDispozici);
    }

    public function sJenKDispozici(): self
    {
        return new self($this->velikost, $this->strih, $this->kategorie, $this->jenIds, true);
    }
}
