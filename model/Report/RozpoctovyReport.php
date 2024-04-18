<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class RozpoctovyReport
{
    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(
        ?string $format,
        string $doSouboru = null,
    )
    {
        $rolePodleRoku                    = $this->poctyRoliPodleRoku();
        $nakupyPodleRoku                  = $this->nakupyPodleRoku();
        $ucastNaAktivitachPodleRoku       = $this->ucastNaAktivitachPodleRoku();
        $prumernaKapacitaAktivitPodleRoku = $this->prumernaKapacitaAktivitPodleRoku();
        $prumernyPocetVypravecuPodleRoku  = $this->prumernyPocetVypravecuPodleRoku();
        $pocetNeorgVypravecuPodleRoku     = $this->pocetNeorgVypravecuPodleRoku();
        $pocetOrgVypravecuPodleRoku       = $this->pocetOrgVypravecuPodleRoku();
        $pocetOrgVypravecuPodleRoku       = $this->bonusyZaAktivityPodleRoku();
        $coze                             = 1;
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function prumernaKapacitaAktivitPodleRoku(): array
    {
        $kapacitaAktivitPodleRoku = [];
        foreach ($this->nactPrumernouKapacituAktivitPodleRoku() as $prumernaKapacitaVRoce) {
            $typAktivity             = (int) $prumernaKapacitaVRoce['typ'];
            $rok                     = (int) $prumernaKapacitaVRoce['rok'];
            $kapacita                = (int) $prumernaKapacitaVRoce['celkova_kapacita'];
            $delka                   = (int) $prumernaKapacitaVRoce['delka'];
            $kapacitaNaJednotkuPrace = $this->naJednotkuPrace($kapacita, $delka);

            $kapacitaAktivitPodleRoku[$typAktivity][$rok][] = $kapacitaNaJednotkuPrace;
        }
        foreach ($kapacitaAktivitPodleRoku as $typAktivity => $kapacitaAktivitVRoce) {
            foreach ($kapacitaAktivitVRoce as $rok => $kapacitaAktivit) {
                $kapacitaAktivitPodleRoku[$typAktivity][$rok] = array_sum($kapacitaAktivit) / count($kapacitaAktivit);
            }
        }

        return $kapacitaAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function prumernyPocetVypravecuPodleRoku(): array
    {
        $pocetVypravecuAktivitPodleRoku = [];
        foreach ($this->nactiPrumernyPocetVypravecuPodleRoku() as $prumernyPocetVypravecuVRoce) {
            $typAktivity                  = (int) $prumernyPocetVypravecuVRoce['typ'];
            $rok                          = (int) $prumernyPocetVypravecuVRoce['rok'];
            $prumernyPocet                = (int) $prumernyPocetVypravecuVRoce['prumerny_pocet'];
            $delka                        = (int) $prumernyPocetVypravecuVRoce['delka'];
            $prumernyPocetNaJednotkuPrace = $this->naJednotkuPrace($prumernyPocet, $delka);

            $pocetVypravecuAktivitPodleRoku[$typAktivity][$rok][] = $prumernyPocetNaJednotkuPrace;
        }
        foreach ($pocetVypravecuAktivitPodleRoku as $typAktivity => $pocetVypravecuAktivitVRoce) {
            foreach ($pocetVypravecuAktivitVRoce as $rok => $pocetVypravecuAktivit) {
                $pocetVypravecuAktivitPodleRoku[$typAktivity][$rok] = array_sum($pocetVypravecuAktivit) / count($pocetVypravecuAktivit);
            }
        }

        return $pocetVypravecuAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function pocetNeorgVypravecuPodleRoku(): array
    {
        $pocetNeorgVypravecuAktivitPodleRoku = [];
        foreach ($this->nactiPocetNeorgVypravecuPodleRoku() as $pocetVypravecuVRoce) {
            $typAktivity          = (int) $pocetVypravecuVRoce['typ'];
            $rok                  = (int) $pocetVypravecuVRoce['rok'];
            $pocet                = (int) $pocetVypravecuVRoce['pocet'];
            $delka                = (int) $pocetVypravecuVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocet, $delka);

            $pocetNeorgVypravecuAktivitPodleRoku[$typAktivity][$rok] ??= 0;
            $pocetNeorgVypravecuAktivitPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $pocetNeorgVypravecuAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function pocetOrgVypravecuPodleRoku(): array
    {
        $pocetOrgVypravecuAktivitPodleRoku = [];
        foreach ($this->nactiPocetOrgVypravecuPodleRoku() as $pocetVypravecuVRoce) {
            $typAktivity          = (int) $pocetVypravecuVRoce['typ'];
            $rok                  = (int) $pocetVypravecuVRoce['rok'];
            $pocet                = (int) $pocetVypravecuVRoce['pocet'];
            $delka                = (int) $pocetVypravecuVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocet, $delka);

            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] ??= 0;
            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $pocetOrgVypravecuAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function bonusyZaAktivityPodleRoku(): array
    {
        $pocetOrgVypravecuAktivitPodleRoku = [];
        foreach ($this->nactiBonusyZaAktivityPodleRoku() as $bonusyVRoce) {
            $typAktivity          = (int) $bonusyVRoce['typ'];
            $rok                  = (int) $bonusyVRoce['rok'];
            $pocet                = (int) $bonusyVRoce['pocet'];
            $delka                = (int) $bonusyVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocet, $delka);

            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] ??= 0;
            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $pocetOrgVypravecuAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, array<int, int>>>
     */
    private function ucastNaAktivitachPodleRoku(): array
    {
        $ucastNaAktivitachPodleRoku = [];
        foreach ($this->nactiUcastNaAktivitachPodleRoku() as $ucastNaAktivitachVRoce) {
            $typAktivity          = (int) $ucastNaAktivitachVRoce['typ'];
            $rok                  = (int) $ucastNaAktivitachVRoce['rok'];
            $pocet                = (int) $ucastNaAktivitachVRoce['pocet'];
            $delka                = (int) $ucastNaAktivitachVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocet, $delka);

            $ucastNaAktivitachPodleRoku[$typAktivity][$rok] ??= 0;
            $ucastNaAktivitachPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $ucastNaAktivitachPodleRoku;
    }

    private function naJednotkuPrace(int $hodnota, int $delka): int
    {
        if ($delka === 0) {
            return 0;
        }
        $hodnotyNaJednotkuPrace = $this->systemoveNastaveni->bonusyZaVedeniAktivity($hodnota);
        foreach ($hodnotyNaJednotkuPrace as $nejmensiCas => $hodnotaNaJednotkuPrace) {
            if ($delka <= $nejmensiCas) {
                return $hodnotaNaJednotkuPrace;
            }
        }

        return 0;
    }

    /**
     * @return array<int, array<int, array<int, int>>>
     */
    private function nakupyPodleRoku(): array
    {
        $nakupyPodleRoku = [];
        foreach ($this->nactiNakupyPodleRoku() as $nakupPodleRoku) {
            $typPredmetu = (int) $nakupPodleRoku['typ_predmetu'];
            $idPredmetu  = (int) $nakupPodleRoku['id_predmetu'];
            $rok         = (int) $nakupPodleRoku['rok'];
            $pocet       = (int) $nakupPodleRoku['pocet'];

            $nakupyPodleRoku[$typPredmetu][$idPredmetu][$rok] ??= 0;
            $nakupyPodleRoku[$typPredmetu][$idPredmetu][$rok] += $pocet;
        }

        return $nakupyPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function poctyRoliPodleRoku(): array
    {
        $poctyRoliPodleRoku = [];
        $letos              = (int) date('Y');
        foreach ($this->ziskaniRoliPodleRoku() as $ziskaniRolePodleRoku) {
            $odRoku = (int) $ziskaniRolePodleRoku['rok'];
            for ($rok = $odRoku; $rok <= $letos; $rok++) {
                $idRole = (int) $ziskaniRolePodleRoku['id_role'];
                $pocet  = (int) $ziskaniRolePodleRoku['pocet'];

                $poctyRoliPodleRoku[$idRole][$rok] ??= 0;
                $poctyRoliPodleRoku[$idRole][$rok] += $pocet;
            }
        }
        foreach ($this->ztrataRoliPodleRoku() as $ztrataRolePodleRoku) {
            $odRoku = (int) $ztrataRolePodleRoku['rok'];
            for ($rok = $odRoku; $rok <= $letos; $rok++) {
                $idRole = (int) $ztrataRolePodleRoku['id_role'];
                $pocet  = (int) $ziskaniRolePodleRoku['pocet'];

                $poctyRoliPodleRoku[$idRole][$rok] = ($poctyRoliPodleRoku[$idRole][$rok] ?? 0) - $pocet;
            }
        }

        return $poctyRoliPodleRoku;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactPrumernouKapacituAktivitPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT typ,
       rok,
       kapacita + kapacita_f + kapacita_m AS celkova_kapacita,
       CASE
            WHEN zacatek IS NULL OR konec IS NULL THEN NULL
            WHEN zacatek > konec THEN TIMESTAMPDIFF(HOUR, zacatek, konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, zacatek, konec)
        END AS delka
FROM akce_seznam
GROUP BY akce_seznam.typ, akce_seznam.rok, celkova_kapacita
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPrumernyPocetVypravecuPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(akce_organizatori.id_uzivatele) / COUNT(DISTINCT akce_seznam.id_akce) AS prumerny_pocet,
       CASE
            WHEN akce_seznam.zacatek IS NULL OR akce_seznam.konec IS NULL THEN 0
            WHEN akce_seznam.zacatek > akce_seznam.konec THEN TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec)
        END AS delka
FROM akce_seznam
LEFT JOIN akce_organizatori
    ON akce_seznam.id_akce = akce_organizatori.id_akce
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetNeorgVypravecuPodleRoku(): array
    {
        return $this->nactiPocetRoliPodleRoku([
            Role::VYZNAM_VYPRAVEC,
            Role::VYZNAM_PUL_ORG_TRICKO,
            Role::VYZNAM_PUL_ORG_UBYTKO,
        ]);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetOrgVypravecuPodleRoku(): array
    {
        return $this->nactiPocetRoliPodleRoku([
            Role::VYZNAM_ORGANIZATOR_ZDARMA,
            Role::VYZNAM_CESTNY_ORGANIZATOR,
            Role::VYZNAM_PARTNER,
            Role::VYZNAM_VYPRAVECSKA_SKUPINA,
        ]);
    }

    /**
     * @param array<string> $vyznamyRoli
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetRoliPodleRoku(array $vyznamyRoli): array
    {
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(DISTINCT akce_organizatori.id_uzivatele) AS pocet,
       CASE
            WHEN akce_seznam.zacatek IS NULL OR akce_seznam.konec IS NULL THEN 0
            WHEN akce_seznam.zacatek > akce_seznam.konec THEN TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec)
        END AS delka
FROM akce_seznam
LEFT JOIN akce_organizatori
    ON akce_seznam.id_akce = akce_organizatori.id_akce
LEFT JOIN uzivatele_role
    ON akce_organizatori.id_uzivatele = uzivatele_role.id_uzivatele
LEFT JOIN role_seznam
    ON uzivatele_role.id_role = role_seznam.id_role
    AND role_seznam.vyznam_role IN ($0)
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
            [
                0 => $vyznamyRoli,
            ],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiBonusyZaAktivityPodleRoku(): array
    {
        $kromeVyznamuRoli = [
            Role::VYZNAM_ORGANIZATOR_ZDARMA,
            Role::VYZNAM_PARTNER,
            Role::VYZNAM_VYPRAVECSKA_SKUPINA,
        ];
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       CASE
            WHEN akce_seznam.nedava_bonus = 1 THEN 0
            WHEN akce_seznam.zacatek IS NULL OR akce_seznam.konec IS NULL THEN 0
            WHEN akce_seznam.zacatek > akce_seznam.konec THEN TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec)
        END AS delka,
        COUNT(DISTINCT akce_organizatori.id_uzivatele) AS pocet_organizatoru
FROM akce_seznam
LEFT JOIN akce_organizatori
    ON akce_seznam.id_akce = akce_organizatori.id_akce
LEFT JOIN uzivatele_role
    ON akce_organizatori.id_uzivatele = uzivatele_role.id_uzivatele
LEFT JOIN role_seznam
    ON uzivatele_role.id_role = role_seznam.id_role
    AND role_seznam.vyznam_role NOT IN ($0)
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
            [
                0 => $kromeVyznamuRoli,
            ],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiUcastNaAktivitachPodleRoku(): array
    {
        $stavy    = [StavPrihlaseni::PRIHLASEN_A_DORAZIL, StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK];
        $stavySql = implode(',', $stavy);

        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(*) AS pocet,
       CASE
            WHEN zacatek IS NULL OR konec IS NULL THEN NULL
            WHEN zacatek > konec THEN TIMESTAMPDIFF(HOUR, zacatek, konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, zacatek, konec)
        END AS delka
FROM akce_seznam
JOIN akce_prihlaseni
    ON akce_seznam.id_akce = akce_prihlaseni.id_akce 
WHERE akce_prihlaseni.id_stavu_prihlaseni IN ({$stavySql})
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiNakupyPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT shop_predmety.typ AS typ_predmetu, shop_predmety.id_predmetu, shop_nakupy.rok, COUNT(*) AS pocet
FROM shop_predmety
JOIN shop_nakupy
    ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
GROUP BY shop_predmety.typ, shop_predmety.id_predmetu, shop_nakupy.rok
SQL,
        );
    }

    private function ziskaniRoliPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT id_role, YEAR(kdy) AS rok, COUNT(*) AS pocet
FROM uzivatele_role_log AS posazen
WHERE posazen.zmena = 'posazen'
GROUP BY posazen.id_role, posazen.kdy
ORDER BY posazen.id_role, posazen.kdy;
SQL,
        );
    }

    private function ztrataRoliPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT id_role, id_uzivatele, YEAR(kdy) AS rok, COUNT(*) AS pocet
FROM uzivatele_role_log AS sesazen
WHERE sesazen.zmena = 'sesazen'
GROUP BY sesazen.id_role, sesazen.kdy
ORDER BY sesazen.id_role, sesazen.kdy;
SQL,
        );
    }

    private function poctyUcastniku()
    {
        $prihlasen = Role::VYZNAM_PRIHLASEN;
        $pritomen  = Role::VYZNAM_PRITOMEN;
        $ucast     = Role::TYP_UCAST;

        return dbFetchAll(<<<SQL
SELECT
    `účastníci (obyč)`,
    `orgové - full`,
    `orgové - ubytko`,
    `orgové - trička`,
    `vypravěči`,
    `dobrovolníci sr`,
    `partneři`,
    `brigádníci`
FROM (
    SELECT
        rocnik_role,
        SUM(IF(registrace, 1, 0)) AS Registrovaných,
        SUM(IF(dorazeni, 1, 0)) AS Dorazilo,
        SUM(
            IF(
                dorazeni AND EXISTS(
                SELECT * FROM uzivatele_role_log AS posazen
                    LEFT JOIN uzivatele_role_log AS sesazen
                        ON sesazen.id_role = posazen.id_role
                               AND sesazen.id_uzivatele =posazen.id_uzivatele
                               AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3
                    AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */
                    AND posazen.id_uzivatele = podle_roku.id_uzivatele
                    AND posazen.id_role IN (?)
                ),
                1,
                0
            )
        )
    FROM (
        SELECT
            role_seznam.rocnik_role,
            uzivatele_role.id_role,
            role_seznam.vyznam_role = '$prihlasen' AS registrace,
            role_seznam.vyznam_role = '$pritomen' AS dorazeni,
            uzivatele_role.id_uzivatele
            FROM uzivatele_role AS uzivatele_role
            JOIN role_seznam
                ON uzivatele_role.id_role = role_seznam.id_role
            WHERE role_seznam.typ_role = '$ucast'
    ) AS podle_roku
    GROUP BY rocnik_role
) AS pocty
SQL,
        );
    }
}
