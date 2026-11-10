<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

/**
 * Years offered by the year switcher of a line page (`?rocnik=`) or of an organiser's page.
 *
 * @see web/moduly/aktivity.php the switcher
 * @see \Gamecon\ArchivPrednasek\PrednaskyNaWebu links of the lecture archive to the line page
 */
final class RocnikyAktivit
{
    /**
     * Years with at least one card under the same conditions as the activities of the page (FiltrAktivity::JEN_VIDITELNE
     * and BEZ_DALSICH_KOL), otherwise a year without a single card could be chosen (an organiser who led only a semifinal
     * of DrD). The current year is always there, so that one can go back.
     *
     * @return list<int> from the newest
     */
    public static function nabizene(int $letosniRocnik, ?int $idTypu = null, ?int $idOrganizatora = null): array
    {
        $parametry = [
            0 => $letosniRocnik,
            1 => StavAktivity::bezneViditelneStavy(),
            2 => TypAktivity::interniTypy(),
            3 => StavAktivity::probehnuteStavy(),
            4 => $idTypu,
            5 => $idOrganizatora,
            6 => [TypAktivity::DRD, TypAktivity::LKD],
        ];
        $rocniky = dbOneArray(<<<'SQL'
            SELECT DISTINCT akce_seznam.rok
            FROM akce_seznam
            WHERE akce_seznam.rok <= $0
                AND akce_seznam.stav IN ($1)
                AND NOT (akce_seznam.typ IN ($2) AND akce_seznam.stav IN ($3))
                AND NOT (akce_seznam.typ IN ($6) AND akce_seznam.cena = 0)
                AND ($4 IS NULL OR akce_seznam.typ = $4)
                AND ($5 IS NULL OR akce_seznam.id_akce IN (
                    SELECT akce_organizatori.id_akce FROM akce_organizatori WHERE akce_organizatori.id_uzivatele = $5
                ))
            ORDER BY akce_seznam.rok DESC
            SQL, $parametry);

        return array_values(array_unique(array_merge([$letosniRocnik], array_map('intval', $rocniky))));
    }
}
