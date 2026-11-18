<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

/**
 * Hlavní místnost aktivity. V `akce_seznam.id_hlavni_lokace` je jen místnost, kterou organizátor výslovně zvolil
 * a která je mezi místnostmi aktivity. NULL znamená „automaticky“: hlavní je první z místností aktivity v pořadí
 * místností (@see Lokace::porovnejPodlePoradi). Kdo hlavní místnost čte, ať v PHP nebo v SQL, používá pravidlo odsud.
 */
final class HlavniLokace
{
    /**
     * @param array<Lokace> $lokaceAktivity
     */
    public static function vyber(
        array $lokaceAktivity,
        ?int  $idZvoleneHlavniLokace,
    ): ?Lokace {
        $prvniPodlePoradi = null;
        foreach ($lokaceAktivity as $lokace) {
            if ($idZvoleneHlavniLokace !== null && $lokace->id() === $idZvoleneHlavniLokace) {
                return $lokace;
            }
            if ($prvniPodlePoradi === null || Lokace::porovnejPodlePoradi($lokace, $prvniPodlePoradi) < 0) {
                $prvniPodlePoradi = $lokace;
            }
        }

        return $prvniPodlePoradi;
    }

    /**
     * SQL výraz s ID hlavní místnosti aktivity z tabulky nebo aliasu $tabulkaAktivity (sloupce `id_akce`
     * a `id_hlavni_lokace`), NULL pro aktivitu bez místností. Totéž pravidlo jako @see vyber
     */
    public static function getSqlId(string $tabulkaAktivity = 'akce_seznam'): string
    {
        return <<<SQL
            COALESCE(
                (SELECT akce_lokace.id_lokace
                    FROM akce_lokace
                    WHERE akce_lokace.id_akce = {$tabulkaAktivity}.id_akce
                        AND akce_lokace.id_lokace = {$tabulkaAktivity}.id_hlavni_lokace),
                (SELECT akce_lokace.id_lokace
                    FROM akce_lokace
                    JOIN lokace ON lokace.id_lokace = akce_lokace.id_lokace
                    WHERE akce_lokace.id_akce = {$tabulkaAktivity}.id_akce
                    ORDER BY lokace.poradi, lokace.id_lokace
                    LIMIT 1)
            )
            SQL;
    }
}
