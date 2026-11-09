<?php

declare(strict_types=1);

namespace Gamecon\Web;

final readonly class VysledekPrevoduObrazku
{
    /**
     * @param list<PolozkaPrevoduObrazku> $polozky
     * @param int $nepouzitychObrazku obrázky v adresáři, na které žádný text neodkazuje a které se proto nechaly
     * @param int $odkazuNaChybejiciSoubor kolik různých obrázků texty zmiňují, ale na disku nejsou
     * @param array<string, int> $prepsanoRadku název tabulky => kolik jejích řádků dostalo nové odkazy
     * @param int $radkuZmenenychSoubezne řádky, jejichž text se mezi načtením a zápisem změnil, takže se nepřepsaly
     */
    public function __construct(
        public array $polozky,
        public int $nepouzitychObrazku = 0,
        public int $odkazuNaChybejiciSoubor = 0,
        public array $prepsanoRadku = [],
        public int $radkuZmenenychSoubezne = 0,
    ) {
    }

    /**
     * @return array<string, int> hodnota @see StavPrevoduObrazkuEnum => počet obrázků v tomhle stavu
     */
    public function getPoctyPodleStavu(): array
    {
        $pocty = [];
        foreach ($this->polozky as $polozka) {
            $pocty[$polozka->stav->value] = ($pocty[$polozka->stav->value] ?? 0) + 1;
        }

        return $pocty;
    }

    public function getUsporaBajtu(): int
    {
        return array_sum(array_map(
            static fn (PolozkaPrevoduObrazku $polozka): int => $polozka->getUsporaBajtu(),
            $this->polozky,
        ));
    }
}
