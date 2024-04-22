<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
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
        $poctyVyznamuRoliPodleRoku            = $this->poctyVyznamuRoliPodleRoku();
        $dobrovolneVstupnePodleRoku           = $this->dobrovolneVstupnePodleRoku();
        $poctyProdanychPredmetuPodleRoku      = $this->poctyProdanychPredmetuPodleRoku();
        $ucastNaAktivitachPodleRoku           = $this->ucastNaAktivitachPodleRoku();
        $prumernaKapacitaAktivitPodleRoku     = $this->prumernaKapacitaAktivitPodleRoku();
        $prumernyPocetVypravecuPodleRoku      = $this->prumernyPocetVypravecuPodleRoku();
        $pocetNeorgVypravecuPodleRoku         = $this->pocetNeorgVypravecuPodleRoku();
        $pocetOrgVypravecuPodleRoku           = $this->pocetOrgVypravecuPodleRoku();
        $bonusyZaAktivityPodleRoku            = $this->bonusyZaAktivityPodleRoku();
        $poctyProdanychAktivitPodleRoku       = $this->poctyProdanychAktivitPodleRoku();
        $pocetOrguZdarmaNaAktivitachPodleRoku = $this->pocetOrguZdarmaNaAktivitachPodleRoku();
        $pocetNeorgTricekPodleRoku            = $this->pocetNeorgTricekPodleRoku();
        $pocetModrychTricekPodleRoku          = $this->pocetModrychTricekPodleRoku();
        $pocetCervenychTricekPodleRoku        = $this->pocetCervenychTricekPodleRoku();
        $pocetModrychTilekPodleRoku           = $this->pocetModrychTilekPodleRoku();
        $pocetCervenychTilekPodleRoku         = $this->pocetCervenychTilekPodleRoku();
        $poctyStornPodleRoku                  = $this->poctyStornPodleRoku();

        $letos       = (int) date('Y');
        $dataReportu = [];
        foreach ($this->pripravOdDo($poctyVyznamuRoliPodleRoku, $letos, 2012) as $vyznamRole => $poctyPodleRoku) {
            $dataReportu[] = [$vyznamRole, ...$poctyPodleRoku];
        }

        $dobrovolneVstupnePodleRoku = ['Dobrovolné vstupné' => $dobrovolneVstupnePodleRoku];
        foreach ($this->pripravOdDo($dobrovolneVstupnePodleRoku, $letos, 2012) as $nazevVstupneho => $sumyPodleRoku) {
            $dataReportu[] = [$nazevVstupneho, ...$sumyPodleRoku];
        }

        $prodanaMistaNaSpani = $this->filtrujMistaNaSpani($poctyProdanychPredmetuPodleRoku);
        foreach ($this->pripravOdDo($prodanaMistaNaSpani, $letos, 2012) as $nazevSpani => $poctyPodleRoku) {
            $dataReportu[] = [$nazevSpani, ...$poctyPodleRoku];
        }

        $report = \Report::zPoli(
            ['datum reportu', ...range($letos, 2012, -1)],
            $dataReportu,
        );
        $report->tFormat($format, $doSouboru);
    }

    /**
     * @param array<int, array<string, array<int,int>>> $poctyProdanychPredmetuPodleRoku
     * @return array<string, array<int,int>>
     */
    private function filtrujMistaNaSpani(array $poctyProdanychPredmetuPodleRoku): array
    {
        return $poctyProdanychPredmetuPodleRoku[TypPredmetu::UBYTOVANI] ?? [];
    }

    /**
     * @param array<string, array<int, int>> $poctyVyznamuRoliPodleRoku
     * @return array<string, array<int,int>>
     */
    private function pripravOdDo(array $poctyVyznamuRoliPodleRoku, int $odRoku, int $doRoku): array
    {
        $poctyVyznamuRoliOdDo = [];
        foreach ($poctyVyznamuRoliPodleRoku as $vyznamRole => $poctyVyznamuRolePodleRoku) {
            for ($rok = $odRoku; $rok >= $doRoku; $rok--) {
                $poctyVyznamuRoliOdDo[$vyznamRole][] = $poctyVyznamuRolePodleRoku[$rok] ?? 0;
            }
        }

        return $poctyVyznamuRoliOdDo;
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
        foreach ($this->nactiPocetNeorgVypravecuNaAktivitachPodleRoku() as $pocetVypravecuVRoce) {
            $typAktivity          = (int) $pocetVypravecuVRoce['typ'];
            $rok                  = (int) $pocetVypravecuVRoce['rok'];
            $pocetOrganizatoru    = (int) $pocetVypravecuVRoce['pocet_organizatoru'];
            $delka                = (int) $pocetVypravecuVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetOrganizatoru, $delka);

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
        foreach ($this->nactiPocetOrgVypravecuNaAktivitachPodleRoku() as $pocetVypravecuVRoce) {
            $typAktivity          = (int) $pocetVypravecuVRoce['typ'];
            $rok                  = (int) $pocetVypravecuVRoce['rok'];
            $pocetOrganizatoru    = (int) $pocetVypravecuVRoce['pocet_organizatoru'];
            $delka                = (int) $pocetVypravecuVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetOrganizatoru, $delka);

            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] ??= 0;
            $pocetOrgVypravecuAktivitPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $pocetOrgVypravecuAktivitPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function pocetOrguZdarmaNaAktivitachPodleRoku(): array
    {
        $pocetOrguZdarmaNaAktivitachPodleRoku = [];
        foreach ($this->nactiPocetOrguZdarmaNaAktivitachPodleRoku() as $pocetOrguZdarmaVRoce) {
            $typAktivity          = (int) $pocetOrguZdarmaVRoce['typ'];
            $rok                  = (int) $pocetOrguZdarmaVRoce['rok'];
            $pocetOrganizatoru    = (int) $pocetOrguZdarmaVRoce['pocet_organizatoru'];
            $delka                = (int) $pocetOrguZdarmaVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetOrganizatoru, $delka);

            $pocetOrguZdarmaNaAktivitachPodleRoku[$typAktivity][$rok] ??= 0;
            $pocetOrguZdarmaNaAktivitachPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $pocetOrguZdarmaNaAktivitachPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function bonusyZaAktivityPodleRoku(): array
    {
        $bonusyZaAktivityPodleRoku = [];
        foreach ($this->nactiBonusyZaAktivityPodleRoku() as $bonusyVRoce) {
            $typAktivity          = (int) $bonusyVRoce['typ'];
            $rok                  = (int) $bonusyVRoce['rok'];
            $pocetOrganizatoru    = (int) $bonusyVRoce['pocet_organizatoru'];
            $delka                = (int) $bonusyVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetOrganizatoru, $delka);

            $bonusyZaAktivityPodleRoku[$typAktivity][$rok] ??= 0;
            $bonusyZaAktivityPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $bonusyZaAktivityPodleRoku;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function poctyProdanychAktivitPodleRoku(): array
    {
        $poctyProdanychAktivitPodleRoku = [];
        foreach ($this->nactiPoctyProdanychAktivitPodleRoku() as $poctyVRoce) {
            $typAktivity          = (int) $poctyVRoce['typ'];
            $rok                  = (int) $poctyVRoce['rok'];
            $pocetAktivit         = (int) $poctyVRoce['pocet_aktivit'];
            $delka                = (int) $poctyVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetAktivit, $delka);

            $poctyProdanychAktivitPodleRoku[$typAktivity][$rok] ??= 0;
            $poctyProdanychAktivitPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $poctyProdanychAktivitPodleRoku;
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
            $pocetUcasti          = (int) $ucastNaAktivitachVRoce['pocet_ucasti'];
            $delka                = (int) $ucastNaAktivitachVRoce['delka'];
            $pocetNaJednotkuPrace = $this->naJednotkuPrace($pocetUcasti, $delka);

            $ucastNaAktivitachPodleRoku[$typAktivity][$rok] ??= 0;
            $ucastNaAktivitachPodleRoku[$typAktivity][$rok] += $pocetNaJednotkuPrace;
        }

        return $ucastNaAktivitachPodleRoku;
    }

    /**
     * @return array<int, array<int, array<int, int>>>
     */
    private function poctyStornPodleRoku(): array
    {
        $stornaPodleRoku = [];
        foreach ($this->nactiStornaPodleRoku() as $stornaVRoce) {
            $typAktivity = (int) $stornaVRoce['typ'];
            $rok         = (int) $stornaVRoce['rok'];
            $pocet       = (int) $stornaVRoce['pocet'];

            $stornaPodleRoku[$typAktivity][$rok] ??= 0;
            $stornaPodleRoku[$typAktivity][$rok] += $pocet;
        }

        return $stornaPodleRoku;
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
     * @return array<int, float>
     */
    private function dobrovolneVstupnePodleRoku(): array
    {
        $dobrovolneVstupnePodleRoku = [];
        foreach ($this->nactiDobrovolneVstupnePodleRoku() as $dobrovolneVstupneVRoce) {
            $rok  = (int) $dobrovolneVstupneVRoce['rok'];
            $suma = (float) $dobrovolneVstupneVRoce['suma'];

            $dobrovolneVstupnePodleRoku[$rok] ??= 0.0;
            $dobrovolneVstupnePodleRoku[$rok] += $suma;
        }

        return $dobrovolneVstupnePodleRoku;
    }

    /**
     * @return array<int, array<string, array<int, int>>>
     */
    private function poctyProdanychPredmetuPodleRoku(): array
    {
        $poctyProdanychPredmetuPodleRoku = [];
        foreach ($this->nactiPoctyProdanychPredmetuPodleRoku() as $poctyProdanehoPredmetu) {
            $typPredmetu = (int) $poctyProdanehoPredmetu['typ_predmetu'];
            $nazev       = $poctyProdanehoPredmetu['nazev'];
            $rok         = (int) $poctyProdanehoPredmetu['rok'];
            $pocetNakupu = (int) $poctyProdanehoPredmetu['pocet_nakupu'];

            $poctyProdanychPredmetuPodleRoku[$typPredmetu][$nazev][$rok] ??= 0;
            $poctyProdanychPredmetuPodleRoku[$typPredmetu][$nazev][$rok] += $pocetNakupu;
        }

        return $poctyProdanychPredmetuPodleRoku;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function poctyVyznamuRoliPodleRoku(): array
    {
        $pomocnePoctyVyznamuRoliPodleRoku = [];
        foreach ($this->nactiZiskaniVyznamuRoliPodleRoku() as $ziskaniRolePodleRoku) {
            $idUzivatele  = (int) $ziskaniRolePodleRoku['id_uzivatele'];
            $rok          = (int) $ziskaniRolePodleRoku['rok'];
            $vyznamRole   = $ziskaniRolePodleRoku['vyznam_role'];
            $pocetZiskani = (int) $ziskaniRolePodleRoku['pocet'];

            $pomocnePoctyVyznamuRoliPodleRoku[$idUzivatele][$vyznamRole][$rok] ??= 0;
            $pomocnePoctyVyznamuRoliPodleRoku[$idUzivatele][$vyznamRole][$rok] += $pocetZiskani;
        }
        foreach ($this->nactiZtratuRoliPodleRoku() as $ztrataRolePodleRoku) {
            $idUzivatele = (int) $ztrataRolePodleRoku['id_uzivatele'];
            $vyznamRole  = $ztrataRolePodleRoku['vyznam_role'];
            $rok         = (int) $ztrataRolePodleRoku['rok'];
            $pocetZtrat  = (int) $ztrataRolePodleRoku['pocet'];

            $pomocnePoctyVyznamuRoliPodleRoku[$idUzivatele][$vyznamRole][$rok] ??= 0;
            $pomocnePoctyVyznamuRoliPodleRoku[$idUzivatele][$vyznamRole][$rok] -= $pocetZtrat;
        }
        $poctyVybranychRoliNaUzivatelePodleRoku = [];
        foreach ($pomocnePoctyVyznamuRoliPodleRoku as $idUzivatele => $poctyVyznamuRoliUzivatele) {
            foreach ($poctyVyznamuRoliUzivatele as $vyznamRole => $poctyVyznamuRoliUzivatelePodleRoku) {
                foreach ($poctyVyznamuRoliUzivatelePodleRoku as $rok => $pocetZiskani) {
                    if ($pocetZiskani <= 0) {
                        continue;
                    }
                    $nazevVybraneRole                                                  = $this->nazevVyznamuRole($vyznamRole);
                    $poctyVybranychRoliNaUzivatelePodleRoku[$nazevVybraneRole][$rok]   ??= [];
                    $poctyVybranychRoliNaUzivatelePodleRoku[$nazevVybraneRole][$rok][] = $idUzivatele;
                }
            }
        }

        $poctyVyznamuRoliPodleRoku = [];
        foreach ($poctyVybranychRoliNaUzivatelePodleRoku as $nazevVybraneRole => $poctyRolePodleRoku) {
            foreach ($poctyRolePodleRoku as $rok => $idUzivatelu) {
                $poctyVyznamuRoliPodleRoku[$nazevVybraneRole][$rok] = count(array_unique($idUzivatelu));
            }
        }

        return $poctyVyznamuRoliPodleRoku;
    }

    private function nazevVyznamuRole(string $vyznamRole): string
    {
        return match ($vyznamRole) {
            Role::VYZNAM_BRIGADNIK => 'brigádníci',
            Role::VYZNAM_PARTNER => 'partneři',
            Role::VYZNAM_DOBROVOLNIK_SENIOR => 'dobrovolníci sr',
            Role::VYZNAM_VYPRAVEC => 'vypravěči',
            Role::VYZNAM_PUL_ORG_TRICKO => 'orgové - trička',
            Role::VYZNAM_PUL_ORG_UBYTKO => 'orgové - ubytko',
            Role::VYZNAM_ORGANIZATOR_ZDARMA => 'orgové - full',
            default => 'účastníci (obyč)',
        };
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
    private function nactiPocetNeorgVypravecuNaAktivitachPodleRoku(): array
    {
        return $this->nactiPocetOrguSRolemiNaAktivitachPodleRoku([
            Role::VYZNAM_VYPRAVEC,
            Role::VYZNAM_PUL_ORG_TRICKO,
            Role::VYZNAM_PUL_ORG_UBYTKO,
        ]);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetOrgVypravecuNaAktivitachPodleRoku(): array
    {
        return $this->nactiPocetOrguSRolemiNaAktivitachPodleRoku([
            Role::VYZNAM_ORGANIZATOR_ZDARMA,
            Role::VYZNAM_CESTNY_ORGANIZATOR,
            Role::VYZNAM_PARTNER,
            Role::VYZNAM_VYPRAVECSKA_SKUPINA,
        ]);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetOrguZdarmaNaAktivitachPodleRoku(): array
    {
        return $this->nactiPocetOrguSPravemNaAktivitachPodleRoku([
            Pravo::AKTIVITY_ZDARMA,
        ]);
    }

    /**
     * @param array<string> $vyznamyRoli
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetOrguSRolemiNaAktivitachPodleRoku(array $vyznamyRoli): array
    {
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(DISTINCT akce_organizatori.id_uzivatele) AS pocet_organizatoru,
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
     * @param array<string> $prava
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetOrguSPravemNaAktivitachPodleRoku(array $prava): array
    {
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(DISTINCT akce_organizatori.id_uzivatele) AS pocet_organizatoru,
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
LEFT JOIN prava_role on role_seznam.id_role = prava_role.id_role
    WHERE prava_role.id_prava IN ($0)
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
            [
                0 => $prava,
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
    private function nactiPoctyProdanychAktivitPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       CASE
            WHEN akce_seznam.nedava_bonus = 1 THEN 0
            WHEN akce_seznam.zacatek IS NULL OR akce_seznam.konec IS NULL THEN 0
            WHEN akce_seznam.zacatek > akce_seznam.konec THEN TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec) + 24
            ELSE TIMESTAMPDIFF(HOUR, akce_seznam.zacatek, akce_seznam.konec)
        END AS delka,
        COUNT(*) AS pocet_aktivit
FROM akce_seznam
GROUP BY akce_seznam.typ, akce_seznam.rok, delka
SQL,
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
       COUNT(*) AS pocet_ucasti,
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
    private function nactiStornaPodleRoku(): array
    {
        $technicka             = TypAktivity::TECHNICKA; // výpomoc, jejíž cena se započítá jako bonus vypravěče, který může použít na nákup na GC
        $brigadnicka           = TypAktivity::BRIGADNICKA; // placený "zaměstnanec"
        $prihlasenAleNedorazil = StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL;
        $pozdeZrusil           = StavPrihlaseni::POZDE_ZRUSIL;

        return dbFetchAll(<<<SQL
SELECT akce_seznam.typ,
       akce_seznam.rok,
       COUNT(*) AS pocet
FROM akce_seznam
JOIN akce_prihlaseni_spec
    ON akce_seznam.id_akce = akce_prihlaseni_spec.id_akce 
WHERE akce_seznam.typ NOT IN ($technicka, $brigadnicka)
    AND akce_prihlaseni_spec.id_stavu_prihlaseni IN ($prihlasenAleNedorazil, $pozdeZrusil)
GROUP BY akce_seznam.typ, akce_seznam.rok
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPoctyProdanychPredmetuPodleRoku(): array
    {
        return dbFetchAll(<<<SQL
SELECT shop_predmety.typ AS typ_predmetu, shop_predmety.nazev, shop_nakupy.rok, COUNT(*) AS pocet_nakupu
FROM shop_predmety
JOIN shop_nakupy
    ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
GROUP BY shop_predmety.typ, shop_predmety.id_predmetu, shop_predmety.nazev, shop_nakupy.rok
ORDER BY shop_predmety.typ, shop_predmety.nazev, shop_nakupy.rok
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiDobrovolneVstupnePodleRoku(): array
    {
        $vstupne = TypPredmetu::VSTUPNE;

        return dbFetchAll(<<<SQL
SELECT shop_nakupy.rok, SUM(shop_nakupy.cena_nakupni) AS suma
FROM shop_predmety
JOIN shop_nakupy
    ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_predmety.typ = {$vstupne}
GROUP BY shop_nakupy.rok
SQL,
        );
    }

    private function pocetNeorgTricekPodleRoku(): array
    {
        return $this->sestavPocetDleRoku($this->nactiPocetNeorgTricekPodleRoku());
    }

    private function pocetModrychTricekPodleRoku(): array
    {
        return $this->sestavPocetDleRoku($this->nactiPocetModrychTricekPodleRoku());
    }

    private function pocetCervenychTricekPodleRoku(): array
    {
        return $this->sestavPocetDleRoku($this->nactiPocetCervenychTricekPodleRoku());
    }

    private function pocetModrychTilekPodleRoku(): array
    {
        return $this->sestavPocetDleRoku($this->nactiPocetModrychTilekPodleRoku());
    }

    private function pocetCervenychTilekPodleRoku(): array
    {
        return $this->sestavPocetDleRoku($this->nactiPocetCervenychTilekPodleRoku());
    }

    /**
     * @param array<array<int|string>> $data
     * @return array<int, int>
     */
    private function sestavPocetDleRoku(array $data): array
    {
        $pocet = [];
        foreach ($data as $radek) {
            $rok         = (int) $radek['rok'];
            $pocet[$rok] ??= 0;
            $pocet[$rok] += (int) $radek['pocet'];
        }

        return $pocet;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetNeorgTricekPodleRoku(): array
    {
        $typTricko  = TypPredmetu::TRICKO;
        $orgCervene = 'červené';
        $orgModre   = 'modré';

        return dbFetchAll(<<<SQL
SELECT shop_nakupy.rok, COUNT(*) AS pocet
FROM shop_predmety
JOIN shop_nakupy
    ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_predmety.typ = {$typTricko}
    AND shop_predmety.nazev NOT LIKE '%{$orgCervene}%'
    AND shop_predmety.nazev NOT LIKE '%{$orgModre}%'
GROUP BY shop_nakupy.rok
SQL,
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetCervenychTricekPodleRoku(): array
    {
        return $this->nactiPocetPredmetuSNazvemPodleRoku(
            TypPredmetu::TRICKO,
            ['Tričko', 'červené'],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetModrychTricekPodleRoku(): array
    {
        return $this->nactiPocetPredmetuSNazvemPodleRoku(
            TypPredmetu::TRICKO,
            ['Tričko', 'modré'],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetCervenychTilekPodleRoku(): array
    {
        return $this->nactiPocetPredmetuSNazvemPodleRoku(
            TypPredmetu::TRICKO,
            ['Tílko', 'červené'],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetModrychTilekPodleRoku(): array
    {
        return $this->nactiPocetPredmetuSNazvemPodleRoku(
            TypPredmetu::TRICKO,
            ['Tílko', 'modré'],
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function nactiPocetPredmetuSNazvemPodleRoku(
        int $typPredmetu,
        array $castiNazvu,
    ): array
    {
        $nazevLike = implode('%', $castiNazvu);

        return dbFetchAll(<<<SQL
SELECT shop_nakupy.rok, COUNT(*) AS pocet
FROM shop_predmety
JOIN shop_nakupy
    ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_predmety.typ = {$typPredmetu}
    AND shop_predmety.nazev LIKE '%{$nazevLike}%'
GROUP BY shop_nakupy.rok
SQL,
        );
    }

    private function nactiZiskaniVyznamuRoliPodleRoku(): array
    {
        return $this->nactiZmenuRoliPodleRoku('posazen');
    }

    private function nactiZtratuRoliPodleRoku(): array
    {
        return $this->nactiZmenuRoliPodleRoku('sesazen');
    }

    private function nactiZmenuRoliPodleRoku(string $zmena): array
    {
        return dbFetchAll(<<<SQL
SELECT sesazen.id_uzivatele, YEAR(kdy) AS rok, vyznam_role, COUNT(*) AS pocet
FROM uzivatele_role_log AS sesazen
JOIN role_seznam on sesazen.id_role = role_seznam.id_role
WHERE sesazen.zmena = '{$zmena}'
GROUP BY sesazen.id_uzivatele, rok, vyznam_role
SQL,
        );
    }
}
