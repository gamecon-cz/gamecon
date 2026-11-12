<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice as Klic;

/**
 * Nastavení půjčovny ze systémového nastavení (skupina Půjčovna kostýmů). Testy si ho sestaví přímo,
 * konstanty ze systémového nastavení v nich nejde předefinovat.
 */
final class NastaveniPujcovny
{
    /**
     * @param list<int> $typyAktivit ID typů aktivit, na které jde kostým rezervovat
     */
    public function __construct(
        private readonly bool $katalogZverejnen,
        private readonly array $typyAktivit,
        private readonly int $limitNaOsobu,
        private readonly int $casNaVymenuMinut,
        private readonly int $vydejMinutPredZacatkem,
        private readonly int $vraceniMinutPoKonci,
        private readonly int $uzaverkaHodinPredZacatkem,
    ) {
    }

    public static function zSystemovehoNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self(
            katalogZverejnen: (bool) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_KATALOG_ZVEREJNEN),
            typyAktivit: self::parsujTypyAktivit((string) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_TYPY_AKTIVIT)),
            limitNaOsobu: (int) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_LIMIT_NA_OSOBU),
            casNaVymenuMinut: (int) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_CAS_NA_VYMENU_MINUT),
            vydejMinutPredZacatkem: (int) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_VYDEJ_MINUT_PRED_ZACATKEM),
            vraceniMinutPoKonci: (int) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_VRACENI_MINUT_PO_KONCI),
            uzaverkaHodinPredZacatkem: (int) $systemoveNastaveni->dejHodnotu(Klic::KOSTYMY_UZAVERKA_HODIN_PRED_ZACATKEM),
        );
    }

    /**
     * Nastavení je volný text, takže cokoli jiného než čísla oddělená čárkou se přeskočí.
     *
     * @return list<int>
     */
    public static function parsujTypyAktivit(string $hodnota): array
    {
        $typy = [];
        foreach (explode(',', $hodnota) as $cast) {
            $cast = trim($cast);
            if ($cast !== '' && ctype_digit($cast)) {
                $typy[] = (int) $cast;
            }
        }

        return array_values(array_unique($typy));
    }

    public function isKatalogZverejnen(): bool
    {
        return $this->katalogZverejnen;
    }

    /**
     * @return list<int>
     */
    public function getTypyAktivit(): array
    {
        return $this->typyAktivit;
    }

    public function isTypAktivityPovolen(int $idTypu): bool
    {
        return in_array($idTypu, $this->typyAktivit, true);
    }

    /**
     * 0 = bez limitu
     */
    public function getLimitNaOsobu(): int
    {
        return $this->limitNaOsobu;
    }

    public function getCasNaVymenuMinut(): int
    {
        return $this->casNaVymenuMinut;
    }

    public function getVydejMinutPredZacatkem(): int
    {
        return $this->vydejMinutPredZacatkem;
    }

    public function getVraceniMinutPoKonci(): int
    {
        return $this->vraceniMinutPoKonci;
    }

    public function getUzaverkaHodinPredZacatkem(): int
    {
        return $this->uzaverkaHodinPredZacatkem;
    }
}
