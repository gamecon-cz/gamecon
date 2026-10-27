<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\SqlStruktura\AkceKorekturaSqlStruktura as Sql;
use Gamecon\Aktivita\TypAktivity;

class KorekturyTextuRepository
{
    /**
     * Main activities of the year (one for a family of instances, which share the texts) whose texts
     * are not proofread and are not already waiting in an open proofreading. Technical, brigade and system
     * activities are left out, nobody outside reads their texts.
     *
     * @return array<int, TextyAktivity> by activity ID
     */
    public function dejTextyKExportu(int $rocnik): array
    {
        $radky = dbFetchAll(<<<'SQL'
SELECT akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.popis_kratky, akce_seznam.popis
FROM akce_seznam
LEFT JOIN akce_instance ON akce_instance.id_instance = akce_seznam.patri_pod
WHERE akce_seznam.rok = $0
  AND akce_seznam.probehla_korekce = 0
  AND akce_seznam.typ NOT IN ($1)
  AND (akce_seznam.patri_pod IS NULL OR akce_instance.id_hlavni_akce = akce_seznam.id_akce)
  AND NOT EXISTS(
      SELECT 1
      FROM akce_korektura
      WHERE akce_korektura.id_akce = akce_seznam.id_akce
        AND akce_korektura.stav IN ($2)
  )
ORDER BY akce_seznam.id_akce
SQL,
            [
                0 => $rocnik,
                1 => [...TypAktivity::interniTypy(), TypAktivity::SYSTEMOVA],
                2 => StavKorekturyEnum::otevrene(),
            ],
        );

        $textyPodleAktivit = [];
        foreach ($radky as $radek) {
            $textyPodleAktivit[(int) $radek['id_akce']] = new TextyAktivity(
                (string) $radek['nazev_akce'],
                (string) $radek['popis_kratky'],
                (string) $radek['popis'],
            );
        }

        return $textyPodleAktivit;
    }

    /**
     * @param array<int> $idsAktivit
     *
     * @return array<int, TextyAktivity> by activity ID, a deleted activity is missing
     */
    public function dejAktualniTexty(array $idsAktivit): array
    {
        $radky = dbFetchAll(<<<'SQL'
SELECT id_akce, nazev_akce, popis_kratky, popis
FROM akce_seznam
WHERE id_akce IN ($0)
SQL,
            [
                0 => $idsAktivit,
            ],
        );

        $textyPodleAktivit = [];
        foreach ($radky as $radek) {
            $textyPodleAktivit[(int) $radek['id_akce']] = new TextyAktivity(
                (string) $radek['nazev_akce'],
                (string) $radek['popis_kratky'],
                (string) $radek['popis'],
            );
        }

        return $textyPodleAktivit;
    }

    /**
     * The latest proofread texts of an activity with the same name from an earlier year.
     * There is no link between activities of different years, the name is the best we have.
     *
     * @param array<int, TextyAktivity> $textyPodleAktivit
     *
     * @return array<int, DrivejsiTexty> by activity ID, an activity without an earlier version is missing
     */
    public function dejDrivejsiOpraveneTexty(array $textyPodleAktivit, int $rocnik): array
    {
        if ($textyPodleAktivit === []) {
            return [];
        }

        $radky = dbFetchAll(<<<'SQL'
SELECT nazev_akce, popis_kratky, popis, rok
FROM akce_seznam
WHERE rok < $0
  AND probehla_korekce = 1
  AND nazev_akce IN ($1)
ORDER BY rok DESC, id_akce
SQL,
            [
                0 => $rocnik,
                1 => array_values(array_unique(array_map(
                    static fn (TextyAktivity $texty) => $texty->nazev,
                    $textyPodleAktivit,
                ))),
            ],
        );

        $nejnovejsiPodleNazvu = [];
        foreach ($radky as $radek) {
            // the collation compares names regardless of case, so does this
            $nejnovejsiPodleNazvu[mb_strtolower(trim((string) $radek['nazev_akce']))] ??= new DrivejsiTexty(
                (int) $radek['rok'],
                new TextyAktivity(
                    (string) $radek['nazev_akce'],
                    (string) $radek['popis_kratky'],
                    (string) $radek['popis'],
                ),
            );
        }

        $drivejsiPodleAktivit = [];
        foreach ($textyPodleAktivit as $idAktivity => $texty) {
            $drivejsi = $nejnovejsiPodleNazvu[mb_strtolower(trim($texty->nazev))] ?? null;
            if ($drivejsi !== null) {
                $drivejsiPodleAktivit[$idAktivity] = $drivejsi;
            }
        }

        return $drivejsiPodleAktivit;
    }

    /**
     * Splits the activities into packages for the AI and saves each with its texts as they are now.
     *
     * @param array<int, TextyAktivity> $textyPodleAktivit
     *
     * @return array<int> numbers of the new packages
     */
    public function vytvorBaliky(array $textyPodleAktivit, int $velikostBaliku, int $exportoval): array
    {
        if ($textyPodleAktivit === []) {
            return [];
        }

        $prvniBalik = (int) dbOneCol('SELECT COALESCE(MAX(balik), 0) + 1 FROM akce_korektura');
        $baliky = [];
        foreach (array_chunk($textyPodleAktivit, max(1, $velikostBaliku), true) as $poradi => $textyBaliku) {
            $balik = $prvniBalik + $poradi;
            foreach ($textyBaliku as $idAktivity => $texty) {
                dbInsert(Sql::AKCE_KOREKTURA_TABULKA, [
                    Sql::ID_AKCE              => $idAktivity,
                    Sql::BALIK                => $balik,
                    Sql::STAV                 => StavKorekturyEnum::EXPORTOVANO->value,
                    Sql::PUVODNI_NAZEV        => $texty->nazev,
                    Sql::PUVODNI_POPIS_KRATKY => $texty->popisKratky,
                    Sql::PUVODNI_POPIS        => $texty->popis,
                    Sql::EXPORTOVAL           => $exportoval,
                ]);
            }
            $baliky[] = $balik;
        }

        return $baliky;
    }

    /**
     * @return array<int, int> number of activities by package, of the packages still waiting for the AI's answer
     */
    public function dejCekajiciBaliky(): array
    {
        return array_map('intval', dbFetchPairs(<<<'SQL'
SELECT balik, COUNT(*)
FROM akce_korektura
WHERE stav = $0
GROUP BY balik
ORDER BY balik
SQL,
            [
                0 => StavKorekturyEnum::EXPORTOVANO,
            ],
        ));
    }

    /**
     * @param int|null $balik null for all packages
     *
     * @return array<KorekturaTextu>
     */
    public function dejCekajiciNaOdpoved(?int $balik): array
    {
        return $this->dejPodle(
            'stav = $0 AND ($1 IS NULL OR balik = $1)',
            [
                0 => StavKorekturyEnum::EXPORTOVANO,
                1 => $balik,
            ],
        );
    }

    /**
     * Only the activities still waiting for the AI's answer leave, answered ones are kept for the proofreader.
     */
    public function zrusBalik(int $balik): int
    {
        return dbAffectedOrNumRows(dbQuery(<<<'SQL'
DELETE FROM akce_korektura
WHERE balik = $0 AND stav = $1
SQL,
            [
                0 => $balik,
                1 => StavKorekturyEnum::EXPORTOVANO,
            ],
        ));
    }

    /**
     * @param array<int> $idsAktivit
     *
     * @return array<int, KorekturaTextu> by activity ID
     */
    public function dejOtevrene(array $idsAktivit): array
    {
        $otevrene = [];
        foreach ($this->dejPodle(
            'id_akce IN ($0) AND stav IN ($1)',
            [
                0 => $idsAktivit,
                1 => StavKorekturyEnum::otevrene(),
            ],
        ) as $korektura) {
            $otevrene[$korektura->idAktivity] = $korektura;
        }

        return $otevrene;
    }

    /**
     * @param int|null $limit null for all
     *
     * @return array<KorekturaTextu>
     */
    public function dejKeSchvaleni(?int $limit): array
    {
        return $this->dejPodle(
            'stav = $0 ORDER BY balik, id_korektury' . ($limit !== null
                ? " LIMIT {$limit}"
                : ''),
            [
                0 => StavKorekturyEnum::NAVRZENO,
            ],
        );
    }

    public function pocetVeStavu(StavKorekturyEnum $stav): int
    {
        return (int) dbOneCol('SELECT COUNT(*) FROM akce_korektura WHERE stav = $0', [
            0 => $stav,
        ]);
    }

    public function dej(int $idKorektury): ?KorekturaTextu
    {
        return $this->dejPodle('id_korektury = $0', [
            0 => $idKorektury,
        ])[0] ?? null;
    }

    public function ulozNavrh(
        int $idKorektury,
        ?string $nazev,
        ?string $popisKratky,
        ?string $popis,
        ?string $zmeny,
    ): void {
        dbQuery(<<<'SQL'
UPDATE akce_korektura
SET navrh_nazev = $1, navrh_popis_kratky = $2, navrh_popis = $3, zmeny = $4, stav = $5, navrzeno_kdy = NOW()
WHERE id_korektury = $0
SQL,
            [
                0 => $idKorektury,
                1 => $nazev,
                2 => $popisKratky,
                3 => $popis,
                4 => $zmeny,
                5 => StavKorekturyEnum::NAVRZENO,
            ],
        );
    }

    public function vyrid(int $idKorektury, StavKorekturyEnum $stav, int $vyridil): void
    {
        dbQuery(<<<'SQL'
UPDATE akce_korektura
SET stav = $1, vyridil = $2, vyrizeno_kdy = NOW()
WHERE id_korektury = $0
SQL,
            [
                0 => $idKorektury,
                1 => $stav,
                2 => $vyridil,
            ],
        );
    }

    /**
     * Saves the texts to the whole family of instances and marks them as proofread.
     */
    public function ulozOpraveneTexty(int $idAktivity, TextyAktivity $texty): void
    {
        $patriPod = dbOneCol('SELECT patri_pod FROM akce_seznam WHERE id_akce = $0', [
            0 => $idAktivity,
        ]);
        dbQuery(<<<'SQL'
UPDATE akce_seznam
SET nazev_akce = $0, popis_kratky = $1, popis = $2, probehla_korekce = 1
WHERE id_akce = $3 OR (patri_pod IS NOT NULL AND patri_pod = $4)
SQL,
            [
                0 => $texty->nazev,
                1 => $texty->popisKratky,
                2 => $texty->popis,
                3 => $idAktivity,
                4 => $patriPod !== null
                    ? (int) $patriPod
                    : null,
            ],
        );
    }

    /**
     * @param array<int, mixed> $parametry
     *
     * @return array<KorekturaTextu>
     */
    private function dejPodle(string $podminka, array $parametry): array
    {
        $radky = dbFetchAll(<<<SQL
SELECT *
FROM akce_korektura
WHERE {$podminka}
SQL,
            $parametry,
        );

        return array_map(
            static fn (array $radek) => new KorekturaTextu(
                id: (int) $radek[Sql::ID_KOREKTURY],
                idAktivity: (int) $radek[Sql::ID_AKCE],
                balik: (int) $radek[Sql::BALIK],
                stav: StavKorekturyEnum::from((string) $radek[Sql::STAV]),
                puvodni: new TextyAktivity(
                    (string) $radek[Sql::PUVODNI_NAZEV],
                    (string) $radek[Sql::PUVODNI_POPIS_KRATKY],
                    (string) $radek[Sql::PUVODNI_POPIS],
                ),
                navrhNazev: $radek[Sql::NAVRH_NAZEV],
                navrhPopisKratky: $radek[Sql::NAVRH_POPIS_KRATKY],
                navrhPopis: $radek[Sql::NAVRH_POPIS],
                zmeny: $radek[Sql::ZMENY],
            ),
            $radky,
        );
    }
}
