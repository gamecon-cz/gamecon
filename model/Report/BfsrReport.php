<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Aktivita\AkcePrihlaseniStavy;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Pravo;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Report;
use Gamecon\Role\Role;
use Uzivatel;
use Webmozart\Assert\Assert;

// takzvaný BFSR (Big f**king Sirien report)
class BfsrReport
{
    private ?float $missedPriceCoefficient          = null;
    private ?float $tooLateCanceledPriceCoefficient = null;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(
        ?string $format,
        ?int    $userId,
    ): void {
        $rocnik = $this->systemoveNastaveni->rocnik();

        // Načteme všechny přihlášené uživatele
        $result = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
WHERE (
    EXISTS(SELECT 1 FROM platne_role_uzivatelu AS prihlasen WHERE prihlasen.id_role = $0 AND prihlasen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
    OR EXISTS(SELECT 1 FROM platne_role_uzivatelu AS pritomen WHERE pritomen.id_role = $1 AND pritomen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
    OR EXISTS(SELECT 1 FROM shop_nakupy WHERE uzivatele_hodnoty.id_uzivatele = shop_nakupy.id_uzivatele AND shop_nakupy.rok = $rocnik)
    OR EXISTS(SELECT 1 FROM platby WHERE platby.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platby.rok = $rocnik)
)
    AND IF($2 IS NOT NULL, uzivatele_hodnoty.id_uzivatele = $2, TRUE)
SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC,
                1 => Role::PRITOMEN_NA_LETOSNIM_GC,
                2 => $userId,
            ],
        );

        // Inicializace počítadel
        $vstupneSum                  = 0.0;
        $placeneUbytovani3L          = 0;
        $placeneUbytovani2L          = 0;
        $placeneUbytovani1L          = 0;
        $placeneUbytovaniSpacak      = 0;
        $zdarmaUbytovani3L           = 0;
        $zdarmaUbytovani2L           = 0;
        $zdarmaUbytovani1L           = 0;
        $zdarmaUbytovaniSpacak       = 0;
        $trickaZdarma                = 0;
        $trickaSeSlevou              = 0;
        $trickaPlacena               = 0;
        $trickaVynosyCelkem          = 0.0;
        $trickaSlevyCelkem           = 0.0;
        $tilkaZdarma                 = 0;
        $tilkaSeSlevou               = 0;
        $tilkaPlacena                = 0;
        $tilkaVynosyCelkem           = 0.0;
        $tilkaSlevyCelkem            = 0.0;
        $plackyCelkem                = [];
        $plackyZdarma                = 0;
        $plackyPlacene               = 0;
        $kostkyCelkem                = [];
        $kostkyZdarma                = 0;
        $kostkyPlacene               = 0;
        $nicknackyCelkem             = [];
        $nicknackyZdarma             = 0;
        $nicknackyPlacene            = 0;
        $blokyCelkem                 = [];
        $blokyZdarma                 = 0;
        $blokyPlacene                = 0;
        $ponozkyCelkem               = [];
        $ponozkyZdarma               = 0;
        $ponozkyPlacene              = 0;
        $taskyCelkem                 = [];
        $taskyZdarma                 = 0;
        $taskyPlacene                = 0;
        $jidlaSnidaneCelkem          = [];
        $jidlaSnidaneZdarma          = 0;
        $jidlaSnidaneSeSlevou        = 0;
        $jidlaSnidanePlnePlacene     = 0;
        $jidlaSnidaneVynosyCelkem    = 0.0;
        $jidlaSnidaneSlevyCelkem     = 0.0;
        $jidlaObedyCelkem            = [];
        $jidlaObedyZdarma            = 0;
        $jidlaObedySeSlevou          = 0;
        $jidlaObedyPlnePlacene       = 0;
        $jidlaObedyVynosyCelkem      = 0.0;
        $jidlaObedySlevyCelkem       = 0.0;
        $jidlaVecereCelkem           = [];
        $jidlaVecereZdarma           = 0;
        $jidlaVecereSeSlevou         = 0;
        $jidlaVecerePlnePlacene      = 0;
        $jidlaVecereVynosyCelkem     = 0.0;
        $jidlaVecereSlevyCelkem      = 0.0;
        $costOfFreeActivities        = [];
        $missedActivityFees          = [];
        $tooLateCanceledActivityFees = [];

        $jeZdarma   = fn(
            float $castka,
            float $sleva,
        ): bool => $castka === 0.0 && $sleva > 0.0;
        $jeSeSlevou = fn(
            float $castka,
            float $sleva,
        ): bool => $castka > 0.0 && $sleva > 0.0;

        // Projdeme všechny uživatele a agregujeme data
        while ($r = mysqli_fetch_assoc($result)) {
            $navstevnik = new Uzivatel($r);

            $costOfFreeActivitiesForUser = $this->getCostOfFreeActivitiesForUser($navstevnik, $rocnik);
            foreach ($costOfFreeActivitiesForUser as $costData) {
                $code                        = $costData['code'];
                $value                       = $costData['value'];
                $costOfFreeActivities[$code] ??= 0.0;
                $costOfFreeActivities[$code] += $value;
            }

            $missedActivityFeesForUser = $this->getMissedActivityFeesForUser($navstevnik, $rocnik);
            foreach ($missedActivityFeesForUser as $feeData) {
                $code                      = $feeData['code'];
                $value                     = $feeData['value'];
                $missedActivityFees[$code] ??= 0.0;
                $missedActivityFees[$code] += $value;
            }

            $tooLateCanceledActivityFeesForUser = $this->getTooLateCanceledActivityFeesForUser($navstevnik, $rocnik);
            foreach ($tooLateCanceledActivityFeesForUser as $feeData) {
                $code                               = $feeData['code'];
                $value                              = $feeData['value'];
                $tooLateCanceledActivityFees[$code] ??= 0.0;
                $tooLateCanceledActivityFees[$code] += $value;
            }

            // Použijeme Finance business logiku pro přesné výpočty
            $polozky = $navstevnik->finance()->dejPolozkyProBfgr();

            foreach ($polozky as $polozka) {
                // Ubytování - placené i zdarma
                if ($polozka->typ === TypPredmetu::UBYTOVANI) {
                    $isPaid = $polozka->castka > 0.0;

                    if (str_starts_with($polozka->kodPredmetu, 'spacak_')) {
                        if ($isPaid) {
                            $placeneUbytovaniSpacak++;
                        } else {
                            $zdarmaUbytovaniSpacak++;
                        }
                    } elseif (str_starts_with($polozka->kodPredmetu, '3L_')) {
                        if ($isPaid) {
                            $placeneUbytovani3L++;
                        } else {
                            $zdarmaUbytovani3L++;
                        }
                    } elseif (str_starts_with($polozka->kodPredmetu, '2L_')) {
                        if ($isPaid) {
                            $placeneUbytovani2L++;
                        } else {
                            $zdarmaUbytovani2L++;
                        }
                    } elseif (str_starts_with($polozka->kodPredmetu, '1L_')) {
                        if ($isPaid) {
                            $placeneUbytovani1L++;
                        } else {
                            $zdarmaUbytovani1L++;
                        }
                    } else {
                        throw new \Chyba(
                            sprintf(
                                "Neznámý kód předmětu typu ubytování %s (název '%s')",
                                var_export($polozka->kodPredmetu, true),
                                $polozka->nazev,
                            ),
                        );
                    }
                    continue;
                }

                // Tričko logika (včetně správného zpracování "Tričko/tílko")
                if (Predmet::jeToTricko($polozka->kodPredmetu, $polozka->typ)) {
                    // Započítání výnosů a slev z triček
                    $trickaVynosyCelkem += $polozka->castka;
                    $trickaSlevyCelkem += $polozka->sleva;

                    if ($jeZdarma($polozka->castka, $polozka->sleva)) {
                        $trickaZdarma++;
                    } elseif ($jeSeSlevou($polozka->castka, $polozka->sleva)) {
                        $trickaSeSlevou++;
                    } else {
                        $trickaPlacena++;
                    }
                    continue;
                }

                // Tílko logika (ale ne generic "Tričko/tílko" - to počítáme jako tříčko)
                if (Predmet::jeToTilko($polozka->kodPredmetu, $polozka->typ) && !Predmet::jeToTricko($polozka->nazev, $polozka->typ)) {
                    // Započítání výnosů a slev z tílek
                    $tilkaVynosyCelkem += $polozka->castka;
                    $tilkaSlevyCelkem += $polozka->sleva;

                    if ($jeZdarma($polozka->castka, $polozka->sleva)) {
                        $tilkaZdarma++;
                    } elseif ($jeSeSlevou($polozka->castka, $polozka->sleva)) {
                        $tilkaSeSlevou++;
                    } else {
                        $tilkaPlacena++;
                    }
                    continue;
                }

                // Placky
                if (Predmet::jeToPlacka($polozka->kodPredmetu)) {
                    $plackyCelkemKod                = 'Vr-Placky-' . $polozka->kodPredmetu;
                    $plackyCelkem[$plackyCelkemKod] ??= 0;
                    $plackyCelkem[$plackyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $plackyZdarma++;
                    } else {
                        $plackyPlacene++;
                    }
                    continue;
                }

                // Kostky
                if (Predmet::jeToKostka($polozka->kodPredmetu)) {
                    $kostkyCelkemKod                = 'Vr-Kostky-' . $polozka->kodPredmetu;
                    $kostkyCelkem[$kostkyCelkemKod] ??= 0;
                    $kostkyCelkem[$kostkyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $kostkyZdarma++;
                    } else {
                        $kostkyPlacene++;
                    }
                    continue;
                }

                // Nicknacky
                if (Predmet::jeToNicknack($polozka->kodPredmetu)) {
                    $nicknackyCelkemKod                   = 'Vr-Nicknacky-' . $polozka->kodPredmetu;
                    $nicknackyCelkem[$nicknackyCelkemKod] ??= 0;
                    $nicknackyCelkem[$nicknackyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $nicknackyZdarma++;
                    } else {
                        $nicknackyPlacene++;
                    }
                    continue;
                }

                // Bloky
                if (Predmet::jeToBlok($polozka->kodPredmetu)) {
                    $blokyCelkemKod               = 'Vr-Bloky-' . $polozka->kodPredmetu;
                    $blokyCelkem[$blokyCelkemKod] ??= 0;
                    $blokyCelkem[$blokyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $blokyZdarma++;
                    } else {
                        $blokyPlacene++;
                    }
                    continue;
                }

                // Ponožky
                if (Predmet::jeToPonozka($polozka->kodPredmetu)) {
                    $ponozkyCelkemKod                 = 'Vr-Ponozky-' . $polozka->kodPredmetu;
                    $ponozkyCelkem[$ponozkyCelkemKod] ??= 0;
                    $ponozkyCelkem[$ponozkyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $ponozkyZdarma++;
                    } else {
                        $ponozkyPlacene++;
                    }
                    continue;
                }

                // Tašky
                if (Predmet::jeToTaska($polozka->kodPredmetu)) {
                    $taskyCelkemKod               = 'Vr-Tasky-' . $polozka->kodPredmetu;
                    $taskyCelkem[$taskyCelkemKod] ??= 0;
                    $taskyCelkem[$taskyCelkemKod]++;

                    if ($polozka->castka === 0.0) {
                        $taskyZdarma++;
                    } else {
                        $taskyPlacene++;
                    }
                    continue;
                }

                // Jídla - snídaně
                if (Predmet::jeToSnidane($polozka->kodPredmetu)) {
                    $jidlaSnidaneCelkemKod                      = 'Xr-Jidla-Snidane';
                    $jidlaSnidaneCelkem[$jidlaSnidaneCelkemKod] ??= 0;
                    $jidlaSnidaneCelkem[$jidlaSnidaneCelkemKod]++;

                    // Započítání výnosů z jídla (finální zaplacená částka)
                    $jidlaSnidaneVynosyCelkem += $polozka->castka;
                    // Započítání slev poskytnutých organizací (náklad pro GameCon)
                    $jidlaSnidaneSlevyCelkem += $polozka->sleva;

                    if ($polozka->castka === 0.0) {
                        $jidlaSnidaneZdarma++;
                    } elseif ($polozka->sleva > 0.0) {
                        $jidlaSnidaneSeSlevou++;
                    } else {
                        $jidlaSnidanePlnePlacene++;
                    }
                    continue;
                }
                // Jídla - obědy
                if (Predmet::jeToObed($polozka->kodPredmetu)) {
                    $jidlaObedyCelkemKod                    = 'Xr-Jidla-Obedy';
                    $jidlaObedyCelkem[$jidlaObedyCelkemKod] ??= 0;
                    $jidlaObedyCelkem[$jidlaObedyCelkemKod]++;

                    // Započítání výnosů z jídla (finální zaplacená částka)
                    $jidlaObedyVynosyCelkem += $polozka->castka;
                    // Započítání slev poskytnutých organizací (náklad pro GameCon)
                    $jidlaObedySlevyCelkem += $polozka->sleva;

                    if ($polozka->castka === 0.0) {
                        $jidlaObedyZdarma++;
                    } elseif ($polozka->sleva > 0.0) {
                        $jidlaObedySeSlevou++;
                    } else {
                        $jidlaObedyPlnePlacene++;
                    }
                    continue;
                }
                // Jídla - večeře
                if (Predmet::jeToVecere($polozka->kodPredmetu)) {
                    $jidlaVecereCelkemKod                     = 'Xr-Jidla-Vecere';
                    $jidlaVecereCelkem[$jidlaVecereCelkemKod] ??= 0;
                    $jidlaVecereCelkem[$jidlaVecereCelkemKod]++;

                    // Započítání výnosů z jídla (finální zaplacená částka)
                    $jidlaVecereVynosyCelkem += $polozka->castka;
                    // Započítání slev poskytnutých organizací (náklad pro GameCon)
                    $jidlaVecereSlevyCelkem += $polozka->sleva;

                    if ($polozka->castka === 0.0) {
                        $jidlaVecereZdarma++;
                    } elseif ($polozka->sleva > 0.0) {
                        $jidlaVecereSeSlevou++;
                    } else {
                        $jidlaVecerePlnePlacene++;
                    }
                    continue;
                }

                if (Predmet::jeToVstupneVcas($polozka->typ, $polozka->kodPredmetu)) {
                    Assert::same($navstevnik->finance()->cenaVstupne(), $polozka->castka);
                    // Dobrovolné vstupné
                    $vstupneSum += $polozka->castka;
                    continue;
                }

                throw new \Chyba(
                    sprintf(
                        "Neznámý kód předmětu %s s typem %d (název '%s')",
                        var_export($polozka->kodPredmetu, true),
                        var_export($polozka->typ, true),
                        $polozka->nazev,
                    )
                );
            }
        }

        // Získáme statistiky účastníka
        $participantStats = $this->getParticipantStats($userId);

        $data = [
            ['Ir-Timestamp', 'Timestamp reportu', $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s')],
            ['Vr-Vstupne', 'Dobrovolné vstupné (sum CZK)', $vstupneSum],
            ['Vr-Ubytovani-3L', 'Prodané noci 3L (počet)', $placeneUbytovani3L],
            ['Vr-Ubytovani-2L', 'Prodané noci 2L (počet)', $placeneUbytovani2L],
            ['Vr-Ubytovani-1L', 'Prodané noci 1L (počet)', $placeneUbytovani1L],
            ['Vr-Ubytovani-spac', 'Prodané noci spacáky (počet)', $placeneUbytovaniSpacak],
            ['Nr-UbytovaniZdarma-3L', 'Noci 3L zdarma (počet)', $zdarmaUbytovani3L],
            ['Nr-UbytovaniZdarma-2L', 'Noci 2L zdarma (počet)', $zdarmaUbytovani2L],
            ['Nr-UbytovaniZdarma-1L', 'Noci 1L zdarma (počet)', $zdarmaUbytovani1L],
            ['Nr-UbytovaniZdarma-spac', 'Noci spacáky zdarma (počet)', $zdarmaUbytovaniSpacak],
            ['Ir-Ucast-Ucastnici', 'Počet letos přihlášených normálních účastníků (nespadajících do žádného z dalších Ir-Ucast-)', $participantStats['Ir-Ucast-Ucastnici'] ?? 0],
            ['Ir-Ucast-Org0', 'Počet letos přihlášených úplných orgů', $participantStats['Ir-Ucast-Org0'] ?? 0],
            ['Ir-Ucast-OrgU', 'Počet letos přihlášených orgů s ubytováním', $participantStats['Ir-Ucast-OrgU'] ?? 0],
            ['Ir-Ucast-OrgT', 'Počet letos přihlášených orgů s tričkem', $participantStats['Ir-Ucast-OrgT'] ?? 0],
            ['Ir-Ucast-MiniOrg', 'Počet letos přihlášených mini-orgů', $participantStats['Ir-Ucast-MiniOrg'] ?? 0],
            ['Ir-Ucast-Vypraveci', 'Počet letos přihlášených vypravěčů', $participantStats['Ir-Ucast-Vypraveci'] ?? 0],
            ['Ir-Ucast-Partneri', 'Počet letos přihlášených partnerů', $participantStats['Ir-Ucast-Partneri'] ?? 0],
            ['Ir-Ucast-Brigadnici', 'Počet letos přihlášených brigádníků', $participantStats['Ir-Ucast-Brigadnici'] ?? 0],
            ['Ir-Ucast-Hermani', 'Počet letos přihlášených hermanů, kteří souběžně nejsou partneři ani vypravěči', $participantStats['Ir-Ucast-Hermani'] ?? 0],
            ['Xr-Tricka-Zaklad', 'Trička placená - kusy', $trickaPlacena],
            ['Xr-Tricka-Sleva', 'Trička se slevou - kusy', $trickaSeSlevou],
            ['Nr-TrickaZdarma', 'Trička zdarma - kusy', $trickaZdarma],
            ['Xr-Tilka-Zaklad', 'Tílka placená - kusy', $tilkaPlacena],
            ['Xr-Tilka-Sleva', 'Tílka se slevou - kusy', $tilkaSeSlevou],
            ['Nr-TilkaZdarma', 'Tílka zdarma - kusy', $tilkaZdarma],
            ['Vr-Svrsky-Celkem', 'Celkem svršků (trička + tílka) - kusy', $trickaZdarma + $trickaSeSlevou + $trickaPlacena + $tilkaZdarma + $tilkaSeSlevou + $tilkaPlacena],
            ['Vr-Tricka-Celkem', 'Celkem triček - kusy', $trickaZdarma + $trickaSeSlevou + $trickaPlacena],
            ['Vr-Tilka-Celkem', 'Celkem tílek - kusy', $tilkaZdarma + $tilkaSeSlevou + $tilkaPlacena],
            ['Vr-Vynosy-Tricka', 'Výnosy z triček (sum CZK)', $trickaVynosyCelkem],
            ['Vr-Vynosy-Tilka', 'Výnosy z tílek (sum CZK)', $tilkaVynosyCelkem],
            ['Vr-Vynosy-Svrsky-Celkem', 'Celkové výnosy ze svršků - trička + tílka (sum CZK)', $trickaVynosyCelkem + $tilkaVynosyCelkem],
            ['Nr-Slevy-Tricka', 'Slevy na trička - náklad pro GameCon (sum CZK)', $trickaSlevyCelkem],
            ['Nr-Slevy-Tilka', 'Slevy na tílka - náklad pro GameCon (sum CZK)', $tilkaSlevyCelkem],
            ['Nr-Slevy-Svrsky-Celkem', 'Celkové slevy na svršky - trička + tílka - náklad pro GameCon (sum CZK)', $trickaSlevyCelkem + $tilkaSlevyCelkem],
            ['Vr-Placky', 'Placky celkem - kusy', $plackyZdarma + $plackyPlacene],
            ['Ir-Placky-Zdarma', 'Placky zdarma - kusy', $plackyZdarma],
            ['Ir-Kostky-CelkemZdarma', 'Kolik z prodaných kostek (všech typů) je zdarma - kusy', $kostkyZdarma],
        ];

        foreach ($costOfFreeActivities as $code => $value) {
            $data[] = [$code, 'Cena za účast orgů zdarma na programu (s právem "Plná sleva na aktivity" na akci, která není "bez slev") (sum CZK)', $value];
        }

        foreach ($missedActivityFees as $code => $value) {
            $data[] = [$code, '100% storno za nedoražení', $value];
        }

        foreach ($tooLateCanceledActivityFees as $code => $value) {
            $data[] = [$code, '50% storno za pozdní odhlášení', $value];
        }

        $activities = Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [FiltrAktivity::ROK => $rocnik],
            prednacitat: true,
        );

        foreach ($this->getCountOfActivitiesAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Počet aktivit přepočtený na standardní aktivitu (kromě dalších kol LKD a mDrD)', $value];
        }

        foreach ($this->getWeightedAverageCapacityOfActivitiesAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Průměrná kapacita aktivity, vážený průměr podle přepočtu na standardní aktivitu (kromě dalších kol LKD a mDrD)', $value];
        }

        foreach ($this->getWeightedAverageCountActivityNarratorsAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Průměrný počet vypravěčů 1 aktivity, vážený průměr podle přepočtu na standardní aktivitu (kromě dalších kol LKD a mDrD)', $value];
        }

        foreach ($this->getCountOfNonFullOrgsAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Vypravěči nebo Half-orgy (kromě dalších kol LKD a mDrD)', $value];
        }

        foreach ($this->getCountOfFullOrgsAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Orgy (kromě dalších kol LKD a mDrD)', $value];
        }

        $sumOfOrgBonuses = $this->getSumOfOrgBonusesAsStandardActivity($activities);
        foreach ($sumOfOrgBonuses as $code => $value) {
            $data[] = [$code, 'Suma bonusů za vedení aktivit u lidí bez práva "Bez bonusu za vedení aktivit"', $value];
        }

        $data[] = ['Nr-BonusyCelkem', 'Suma všech bonusů za vedení aktivit', array_sum($sumOfOrgBonuses)];

        $technicalActivityBonuses = $this->getSumOfTechnicalActivityBonuses($activities);
        $data[]                   = ['Nr-BonusyTech', 'Suma všech bonusů za účast na technické aktivitě', $technicalActivityBonuses];

        foreach ($this->getSumOfSavedBonusesAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Ušetřené bonusy sekce (full-org vedoucí aktivit bez nároku na bonus)', $value];
        }

        foreach ($this->getCountOfPlayBlocksAsStandardActivity($activities) as $code => $value) {
            $data[] = [$code, 'Počet herních bloků zabraný hráči přepočtený na standardní aktivitu (bez ohledu na kategorii hráče) (kromě dalších kol LKD a mDrD)', $value];
        }

        foreach ($this->getSumOfEarnings($activities) as $code => $value) {
            $data[] = [$code, 'Příjmy z aktivit, bez storn a bez lidí co mají účast zdarma', $value];
        }

        $lectureRevenue = $this->getLectureRevenue($activities);
        $data[]         = ['Vr-Prednaska-Ucast', 'Přednášky - standardní účast (pouze na placených přednáškách) - CZK', $lectureRevenue];

        Assert::same(
            array_sum($kostkyCelkem), $kostkyZdarma + $kostkyPlacene,
            'Součet kostek zdarma a placených musí odpovídat celkovému počtu kostek',
        );
        foreach ($kostkyCelkem as $code => $value) {
            $data[] = [$code, 'kostky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($plackyCelkem), $plackyZdarma + $plackyPlacene,
            'Součet placek zdarma a placených musí odpovídat celkovému počtu placek',
        );
        foreach ($plackyCelkem as $code => $value) {
            $data[] = [$code, 'placky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($nicknackyCelkem), $nicknackyZdarma + $nicknackyPlacene,
            'Součet nicknacků zdarma a placených musí odpovídat celkovému počtu nicknacků',
        );
        foreach ($nicknackyCelkem as $code => $value) {
            $data[] = [$code, 'nicknacky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($blokyCelkem), $blokyZdarma + $blokyPlacene,
            'Součet bloků zdarma a placených musí odpovídat celkovému počtu bloků',
        );
        foreach ($blokyCelkem as $code => $value) {
            $data[] = [$code, 'bloky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($ponozkyCelkem), $ponozkyZdarma + $ponozkyPlacene,
            'Součet ponožek zdarma a placených musí odpovídat celkovému počtu ponožek',
        );
        foreach ($ponozkyCelkem as $code => $value) {
            $data[] = [$code, 'ponožky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($taskyCelkem), $taskyZdarma + $taskyPlacene,
            'Součet tašek zdarma a placených musí odpovídat celkovému počtu tašek',
        );
        foreach ($taskyCelkem as $code => $value) {
            $data[] = [$code, 'tašky prodeje - včetně zdarma - kusy', $value];
        }

        Assert::same(
            array_sum($jidlaSnidaneCelkem), $jidlaSnidaneZdarma + $jidlaSnidaneSeSlevou + $jidlaSnidanePlnePlacene,
            'Součet snídaní zdarma, se slevou a placených musí odpovídat celkovému počtu snídaní',
        );
        $data[] = ['Xr-Jidla-Snidane', 'snídaně placené - kusy', $jidlaSnidanePlnePlacene];

        Assert::same(
            array_sum($jidlaObedyCelkem), $jidlaObedyZdarma + $jidlaObedySeSlevou + $jidlaObedyPlnePlacene,
            'Součet obědů zdarma, se slevou a placených musí odpovídat celkovému počtu obědů',
        );
        $data[] = ['Xr-Jidla-Obedy', 'obědy placené - kusy', $jidlaObedyPlnePlacene];

        Assert::same(
            array_sum($jidlaVecereCelkem), $jidlaVecereZdarma + $jidlaVecereSeSlevou + $jidlaVecerePlnePlacene,
            'Součet večeří zdarma, se slevou a placených musí odpovídat celkovému počtu večeří',
        );
        $data[] = ['Xr-Jidla-Vecere', 'večeře placené - kusy', $jidlaVecerePlnePlacene];

        $data[] = ['Nr-JidlaZdarma-Snidane', 'snídaně zdarma - kusy', $jidlaSnidaneZdarma];
        $data[] = ['Nr-JidlaZdarma-Obedy', 'obědy zdarma - kusy', $jidlaObedyZdarma];
        $data[] = ['Nr-JidlaZdarma-Vecere', 'večeře zdarma - kusy', $jidlaVecereZdarma];

        $data[] = ['Nr-JidlaSleva-Snidane', 'snídaně se slevou - kusy', $jidlaSnidaneSeSlevou];
        $data[] = ['Nr-JidlaSleva-Obedy', 'obědy se slevou - kusy', $jidlaObedySeSlevou];
        $data[] = ['Nr-JidlaSleva-Vecere', 'večeře se slevou - kusy', $jidlaVecereSeSlevou];

        $data[] = ['Vr-Vynosy-Snidane', 'Výnosy ze snídaní (sum CZK)', $jidlaSnidaneVynosyCelkem];
        $data[] = ['Vr-Vynosy-Obedy', 'Výnosy z obědů (sum CZK)', $jidlaObedyVynosyCelkem];
        $data[] = ['Vr-Vynosy-Vecere', 'Výnosy z večeří (sum CZK)', $jidlaVecereVynosyCelkem];
        $data[] = ['Vr-Vynosy-Jidla-Celkem', 'Celkové výnosy z jídel (sum CZK)', $jidlaSnidaneVynosyCelkem + $jidlaObedyVynosyCelkem + $jidlaVecereVynosyCelkem];

        $data[] = ['Nr-Slevy-Snidane', 'Slevy na snídaně - náklad pro GameCon (sum CZK)', $jidlaSnidaneSlevyCelkem];
        $data[] = ['Nr-Slevy-Obedy', 'Slevy na obědy - náklad pro GameCon (sum CZK)', $jidlaObedySlevyCelkem];
        $data[] = ['Nr-Slevy-Vecere', 'Slevy na večeře - náklad pro GameCon (sum CZK)', $jidlaVecereSlevyCelkem];
        $data[] = ['Nr-Slevy-Jidla-Celkem', 'Celkové slevy na jídla - náklad pro GameCon (sum CZK)', $jidlaSnidaneSlevyCelkem + $jidlaObedySlevyCelkem + $jidlaVecereSlevyCelkem];

        Report::zPoli(['kod', 'popis', 'data'], $data)->tFormat($format);
    }

    /**
     * Získá statistiky účastníků podle kategorií (org0, orgU, orgT, vypravěči, partneři, atd.)
     * @return array<string, int> Mapa kod => počet
     */
    private function getParticipantStats(?int $userId): array
    {
        $rocnik         = $this->systemoveNastaveni->rocnik();
        $jakykoliRocnik = Role::JAKYKOLI_ROCNIK;

        $VYZNAM_ORGANIZATOR_ZDARMA = Role::VYZNAM_ORGANIZATOR_ZDARMA;
        $VYZNAM_PUL_ORG_UBYTKO     = Role::VYZNAM_PUL_ORG_UBYTKO;
        $VYZNAM_PUL_ORG_TRICKO     = Role::VYZNAM_PUL_ORG_TRICKO;
        $VYZNAM_MINI_ORG           = Role::VYZNAM_MINI_ORG;
        $VYZNAM_VYPRAVEC           = Role::VYZNAM_VYPRAVEC;
        $VYZNAM_PARTNER            = Role::VYZNAM_PARTNER;
        $VYZNAM_HERMAN             = Role::VYZNAM_HERMAN;
        $VYZNAM_BRIGADNIK          = Role::VYZNAM_BRIGADNIK;
        $VYZNAM_PRIHLASEN          = Role::VYZNAM_PRIHLASEN;
        $typRoleUcast              = Role::TYP_UCAST;

        $result = dbQuery(<<<SQL
WITH user_role AS (
    SELECT uzivatele_role.id_uzivatele AS id_uzivatele,
           role_seznam.vyznam_role AS vyznam_role
    FROM uzivatele_role
    JOIN role_seznam ON uzivatele_role.id_role = role_seznam.id_role
    WHERE role_seznam.rocnik_role IN ($rocnik, $jakykoliRocnik)
      AND role_seznam.vyznam_role IN (
          '{$VYZNAM_ORGANIZATOR_ZDARMA}',
          '{$VYZNAM_PUL_ORG_UBYTKO}',
          '{$VYZNAM_PUL_ORG_TRICKO}',
          '{$VYZNAM_MINI_ORG}',
          '{$VYZNAM_VYPRAVEC}',
          '{$VYZNAM_PARTNER}',
          '{$VYZNAM_HERMAN}',
          '{$VYZNAM_BRIGADNIK}'
      )
)
SELECT
    CONCAT('Ir-Ucast-',
        IF(user_role.vyznam_role IS NULL, 'Ucastnici',
            CASE user_role.vyznam_role
                WHEN '{$VYZNAM_ORGANIZATOR_ZDARMA}' THEN 'Org0'
                WHEN '{$VYZNAM_PUL_ORG_UBYTKO}' THEN 'OrgU'
                WHEN '{$VYZNAM_PUL_ORG_TRICKO}' THEN 'OrgT'
                WHEN '{$VYZNAM_MINI_ORG}' THEN 'MiniOrg'
                WHEN '{$VYZNAM_VYPRAVEC}' THEN 'Vypraveci'
                WHEN '{$VYZNAM_PARTNER}' THEN 'Partneri'
                WHEN '{$VYZNAM_BRIGADNIK}' THEN 'Brigadnici'
                WHEN '{$VYZNAM_HERMAN}' THEN 'Hermani'
            END
        )
    ) AS kod,
    COUNT(registered_user.id_uzivatele) AS pocet
FROM (
    SELECT DISTINCT id_uzivatele AS id_uzivatele
    FROM uzivatele_role
    JOIN role_seznam ON uzivatele_role.id_role = role_seznam.id_role
    WHERE typ_role = '{$typRoleUcast}'
        AND role_seznam.vyznam_role = '{$VYZNAM_PRIHLASEN}'
        AND rocnik_role = $rocnik
        AND IF($1 IS NOT NULL, uzivatele_role.id_uzivatele = $1, TRUE)
) AS registered_user
LEFT JOIN user_role ON user_role.id_uzivatele = registered_user.id_uzivatele
WHERE (
    -- Hermany počítat pouze pokud nejsou souběžně ani partneři, ani vypravěči
    -- (pokud je někdo současně herman A partner/vypravěč, nezapočítává se jako herman)
    NOT (
        user_role.vyznam_role = '{$VYZNAM_HERMAN}'
        AND EXISTS(
            SELECT 1
            FROM user_role AS other_role
            WHERE other_role.id_uzivatele = user_role.id_uzivatele
              AND other_role.vyznam_role IN ('{$VYZNAM_PARTNER}', '{$VYZNAM_VYPRAVEC}')
        )
    )
)
GROUP BY user_role.vyznam_role
SQL,
            [
                1 => $userId,
            ],
        );

        $stats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $stats[$row['kod']] = (int)$row['pocet'];
        }

        return $stats;
    }

    /**
     * Získá data o nákladech na aktivity zdarma pro daného uživatele
     * @param Uzivatel $navstevnik
     * @return array<int, array{code: string, value: float}>
     */
    private function getCostOfFreeActivitiesForUser(
        Uzivatel $navstevnik,
        int      $rocnik,
    ): array {
        $activityPriceCoefficient = $navstevnik->finance()->soucinitelCenyAktivit();
        if ($activityPriceCoefficient === 1.0) {
            // účastník nemá žádnou slevu na aktivity
            return [];
        }
        $costOfFreeActivities = [];
        foreach ($navstevnik->aktivityNaKtereDorazil($rocnik) as $aktivita) {
            if ($aktivita->bezSlevy()) {
                continue;
            }
            $costOfFreeActivities[] = [
                'code'  => 'Nr-Zdarma-' . $this->getActivityGroupCode($aktivita),
                'value' => $aktivita->cenaZaklad() - ($aktivita->cenaZaklad() * $activityPriceCoefficient),
            ];
        }

        return $costOfFreeActivities;
    }

    /**
     * Získá data o storno poplatcích za aktivity na které daný uživatel nedorazil
     * @param Uzivatel $navstevnik
     * @return array<int, array{code: string, value: float}>
     */
    private function getMissedActivityFeesForUser(
        Uzivatel $navstevnik,
        int      $rocnik,
    ): array {
        $activityPriceCoefficient = $navstevnik->finance()->soucinitelCenyAktivit();
        $missedPriceCoefficient   = $this->getMissedPriceCoefficient();
        $missedActivityFees       = [];
        foreach ($navstevnik->aktivityNaKtereNedorazil($rocnik) as $activity) {
            // Interní aktivity (technické, brigádnické) nemají storno poplatek
            if (in_array($activity->typId(), TypAktivity::interniTypy(), true)) {
                continue;
            }
            $missedActivityFees[] = [
                'code'  => 'Vr-Storna-100-' . $this->getActivityGroupCode($activity),
                'value' => ($activity->bezSlevy()
                        ? $activity->cenaZaklad()
                        : $activity->cenaZaklad() * $activityPriceCoefficient) * $missedPriceCoefficient,
            ];
        }

        return $missedActivityFees;
    }

    /**
     * Získá data o storno poplatcích za aktivity na které daný uživatel nedorazil
     * @param Uzivatel $navstevnik
     * @param int $rocnik
     * @return array<int, array{code: string, value: float}>
     */
    private function getTooLateCanceledActivityFeesForUser(
        Uzivatel $navstevnik,
        int      $rocnik,
    ): array {
        $activityPriceCoefficient        = $navstevnik->finance()->soucinitelCenyAktivit();
        $tooLateCanceledPriceCoefficient = $this->getTooLateCanceledPriceCoefficient();
        $tooLateCanceledActivityFees     = [];
        foreach ($navstevnik->aktivityKterePozdeZrusil($rocnik) as $aktivita) {
            // Interní aktivity (technické, brigádnické) nemají storno poplatek
            if (in_array($aktivita->typId(), TypAktivity::interniTypy(), true)) {
                continue;
            }
            $tooLateCanceledActivityFees[] = [
                'code'  => 'Vr-Storna-50-' . $this->getActivityGroupCode($aktivita),
                'value' => ($aktivita->bezSlevy()
                        ? $aktivita->cenaZaklad()
                        : $aktivita->cenaZaklad() * $activityPriceCoefficient) * $tooLateCanceledPriceCoefficient,
            ];
        }

        return $tooLateCanceledActivityFees;
    }

    private function getMissedPriceCoefficient(): float
    {
        if ($this->missedPriceCoefficient === null) {
            $missed                       = AkcePrihlaseniStavy::zId(AkcePrihlaseniStavy::NEDORAZIL_ID);
            $this->missedPriceCoefficient = $missed->platbaProcent() / 100;
        }

        return $this->missedPriceCoefficient;
    }

    private function getTooLateCanceledPriceCoefficient(): float
    {
        if ($this->tooLateCanceledPriceCoefficient === null) {
            $canceledTooLate                       = AkcePrihlaseniStavy::zId(AkcePrihlaseniStavy::POZDE_ZRUSIL_ID);
            $this->tooLateCanceledPriceCoefficient = $canceledTooLate->platbaProcent() / 100;
        }

        return $this->tooLateCanceledPriceCoefficient;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getCountOfActivitiesAsStandardActivity(array $activities): array
    {
        $countOfActivitiesAsStandardActivity = [];
        foreach ($activities as $activity) {
            if ($activity->jeToDalsiKolo()) {
                // not the first round of an activity with rounds
                continue;
            }
            $length = $activity->delka();
            $code   = 'Ir-Std-' . $this->getActivityGroupCode($activity);

            $countOfActivitiesAsStandardActivity[$code] ??= 0;
            $countOfActivitiesAsStandardActivity[$code] += $this->getActivityStandardLengthCoefficient($length);
        }

        return $countOfActivitiesAsStandardActivity;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getWeightedAverageCapacityOfActivitiesAsStandardActivity(array $activities): array
    {
        $capacityOfActivitiesAsStandardActivity = [];
        foreach ($activities as $activity) {
            if ($activity->jeToDalsiKolo()) {
                // not the first round of an activity with rounds
                continue;
            }
            $capacity       = $activity->finalniKapacita();
            $standardLength = $this->getActivityStandardLengthCoefficient($activity->delka());
            $code           = 'Ir-Kapacita-' . $this->getActivityGroupCode($activity);

            $capacityOfActivitiesAsStandardActivity[$code]             ??= ['capacity' => 0.0, 'weight' => 0.0];
            $capacityOfActivitiesAsStandardActivity[$code]['capacity'] += $capacity * $standardLength;
            $capacityOfActivitiesAsStandardActivity[$code]['weight']   += $standardLength;
        }
        foreach ($capacityOfActivitiesAsStandardActivity as $code => $data) {
            if ($data['weight'] > 0.0) {
                $capacityOfActivitiesAsStandardActivity[$code] = $data['capacity'] / $data['weight'];
            } else {
                $capacityOfActivitiesAsStandardActivity[$code] = 0.0;
            }
        }

        return $capacityOfActivitiesAsStandardActivity;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getWeightedAverageCountActivityNarratorsAsStandardActivity(array $activities): array
    {
        $countOfNarratorsOfActivitiesAsStandardActivity = [];
        foreach ($activities as $activity) {
            if ($activity->jeToDalsiKolo()) {
                // not the first round of an activity with rounds
                continue;
            }
            $countOfNarrators = count($activity->dejOrganizatoriIds());
            $standardLength   = $this->getActivityStandardLengthCoefficient($activity->delka());
            $code             = 'Ir-PrumPocVyp-' . $this->getActivityGroupCode($activity);

            $countOfNarratorsOfActivitiesAsStandardActivity[$code]           ??= ['value' => 0.0, 'weight' => 0.0];
            $countOfNarratorsOfActivitiesAsStandardActivity[$code]['value']  += $countOfNarrators * $standardLength;
            $countOfNarratorsOfActivitiesAsStandardActivity[$code]['weight'] += $standardLength;
        }

        return $this->getWithWeightenedAverage($countOfNarratorsOfActivitiesAsStandardActivity);
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getCountOfNonFullOrgsAsStandardActivity(array $activities): array
    {
        return $this->getCountOfFilteredOrgsWithRoleAsStandardActivity(
            activities: $activities,
            callback: fn(
                Uzivatel $u,
            ) => !$u->maRoli(Role::ORGANIZATOR),
            codePrefix: 'Ir-StdVypraveci-',
        );
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getCountOfFullOrgsAsStandardActivity(array $activities): array
    {
        return $this->getCountOfFilteredOrgsWithRoleAsStandardActivity(
            activities: $activities,
            callback: fn(
                Uzivatel $u,
            ) => $u->maRoli(Role::ORGANIZATOR),
            codePrefix: 'Ir-StdVypOrgove-',
        );
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getSumOfOrgBonusesAsStandardActivity(array $activities): array
    {
        $bonusForStandardActivity                  = (int)$this->systemoveNastaveni->dejHodnotu(SystemoveNastaveniKlice::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU);
        $countOfOrgsOfActivitiesAsStandardActivity = [];
        foreach ($activities as $activity) {
            $countOfOrgsWithBonus = count(array_filter($activity->organizatori(), fn(
                Uzivatel $u,
            ) => !$u->nemaPravoNaBonusZaVedeniAktivit()));
            $standardLength       = $this->getActivityStandardLengthCoefficient($activity->delka());
            $code                 = 'Nr-Bonusy-' . $this->getActivityGroupCode($activity);

            $countOfOrgsOfActivitiesAsStandardActivity[$code] ??= 0;
            $countOfOrgsOfActivitiesAsStandardActivity[$code] += $countOfOrgsWithBonus * $standardLength * $bonusForStandardActivity;
        }

        return $countOfOrgsOfActivitiesAsStandardActivity;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getSumOfSavedBonusesAsStandardActivity(array $activities): array
    {
        $bonusForStandardActivity = (int)$this->systemoveNastaveni->dejHodnotu(SystemoveNastaveniKlice::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU);
        $savedBonuses             = [];
        foreach ($activities as $activity) {
            $countOfFullOrgs = count(array_filter($activity->organizatori(), fn(
                Uzivatel $u,
            ) => $u->nemaPravoNaBonusZaVedeniAktivit()));
            $standardLength  = $this->getActivityStandardLengthCoefficient($activity->delka());
            $code            = 'Nr-UsetreneBonusy-' . $this->getActivityGroupCode($activity);

            $savedBonuses[$code] ??= 0;
            $savedBonuses[$code] += $countOfFullOrgs * $standardLength * $bonusForStandardActivity;
        }

        return $savedBonuses;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return float
     */
    private function getSumOfTechnicalActivityBonuses(array $activities): float
    {
        $totalTechnicalBonuses = 0.0;
        foreach ($activities as $activity) {
            if ($activity->typId() !== TypAktivity::TECHNICKA) {
                continue;
            }
            $activityPrice = $activity->cenaZaklad();
            foreach ($activity->prihlaseni() as $participant) {
                if ($participant->maPravoNaBonusZaVedeniAktivit()) {
                    $totalTechnicalBonuses += $activityPrice;
                }
            }
        }

        return $totalTechnicalBonuses;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getCountOfPlayBlocksAsStandardActivity(array $activities): array
    {
        $countOfPlayBlocksAsStandardActivity = [];
        foreach ($activities as $activity) {
            if ($activity->jeToDalsiKolo()) {
                // not the first round of an activity with rounds
                continue;
            }
            $length = $activity->delka();
            $code   = 'Ir-Ucast-' . $this->getActivityGroupCode($activity);

            $countOfPlayBlocksAsStandardActivity[$code] ??= 0;
            $countOfPlayBlocksAsStandardActivity[$code] += $this->getActivityStandardLengthCoefficient($length) * count($activity->prihlaseniRawArray());
        }

        return $countOfPlayBlocksAsStandardActivity;
    }

    /**
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getSumOfEarnings(array $activities): array
    {
        $sumOfEarnings = [];
        foreach ($activities as $activity) {
            $code = 'Vr-Vynosy-' . $this->getActivityGroupCode($activity);

            $sumOfEarnings[$code] ??= 0;
            $sumOfEarnings[$code] += array_sum(
                array_map(static fn(
                    Uzivatel $participant,
                ) => $activity->soucinitelCenyAktivity($participant) * $activity->cenaZaklad(),
                    $activity->prihlaseni(),
                ),
            );
        }

        return $sumOfEarnings;
    }

    /**
     * Získá příjmy z placených přednášek (standardní účast)
     *
     * @param array<int, Aktivita> $activities
     * @return float
     */
    private function getLectureRevenue(array $activities): float
    {
        $totalRevenue = 0.0;
        foreach ($activities as $activity) {
            if ($activity->typId() !== TypAktivity::PREDNASKA) {
                continue;
            }
            $activityPrice = $activity->cenaZaklad();
            if ($activityPrice === 0.0) {
                continue;
            }
            foreach ($activity->prihlaseni() as $participant) {
                $totalRevenue += $activity->soucinitelCenyAktivity($participant) * $activityPrice;
            }
        }

        return $totalRevenue;
    }

    /**
     * @param callable(Uzivatel): bool $callback
     * @param array<int, Aktivita> $activities
     * @return array{code: string, value: float}
     */
    private function getCountOfFilteredOrgsWithRoleAsStandardActivity(
        array    $activities,
        callable $callback,
        string   $codePrefix,
    ): array {
        $countOfOrgsOfActivitiesAsStandardActivity = [];
        foreach ($activities as $activity) {
            if ($activity->jeToDalsiKolo()) {
                // not the first round of an activity with rounds
                continue;
            }
            $countOfFilteredOrgs = count(array_filter($activity->organizatori(), $callback));
            $standardLength      = $this->getActivityStandardLengthCoefficient($activity->delka());
            $code                = $codePrefix . $this->getActivityGroupCode($activity);

            $countOfOrgsOfActivitiesAsStandardActivity[$code] ??= 0;
            $countOfOrgsOfActivitiesAsStandardActivity[$code] += $countOfFilteredOrgs * $standardLength;
        }

        return $countOfOrgsOfActivitiesAsStandardActivity;
    }

    /**
     * @param array<string, array{value: float, weight: float}> $data
     * @return array<string, float>
     */
    private function getWithWeightenedAverage(array $data): array
    {
        $result = [];
        foreach ($data as $code => $values) {
            ['value' => $value, 'weight' => $weight] = $values;
            if ($weight > 0.0) {
                $result[$code] = $value / $weight;
            } else {
                $result[$code] = 0.0;
            }
        }

        return $result;
    }

    private function getActivityStandardLengthCoefficient(float $length): float
    {
        return SystemoveNastaveni::getActivityStandardLengthCoefficient($length);
    }

    private function getActivityGroupCode(Aktivita $aktivita): string
    {
        if ($aktivita->typId() === TypAktivity::WARGAMING) {
            return in_array(\Tag::MALOVANI, $aktivita->tagyId(), false)
                ? 'WGmal'
                : 'WGhry';
        }
        if ($aktivita->typId() === TypAktivity::BONUS) {
            return in_array(\Tag::UNIKOVKA, $aktivita->tagyId(), false)
                ? 'AHEsc'
                : 'AHry';
        }

        return $aktivita->typ()->nazev();
    }
}
