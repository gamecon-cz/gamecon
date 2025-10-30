<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Report;
use Gamecon\Role\Role;
use Uzivatel;

// takzvaný BFSR (Big f**king Sirien report)
class BfsrReport
{
    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(?string $format)
    {
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
SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC,
                1 => Role::PRITOMEN_NA_LETOSNIM_GC,
            ],
        );

        // Agregované statistiky
        $stats = [];

        // Inicializace počítadel
        $trickaZdarma = 0;
        $trickaSeSlevou = 0;
        $trickaPlacena = 0;
        $tilkaZdarma = 0;
        $tilkaSeSlevou = 0;
        $tilkaPlacena = 0;
        $plackyZdarma = 0;
        $plackyPlacene = 0;
        $kostkyZdarma = 0;

        $jeZdarma = fn(
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

            // Použijeme Finance business logiku pro přesné výpočty
            $polozky = $navstevnik->finance()->dejPolozkyProBfgr();

            foreach ($polozky as $polozka) {
                ['nazev' => $nazev, 'castka' => $castka, 'sleva' => $sleva, 'typ' => $typ] = $polozka;
                $castka = (float)$castka;
                $sleva = (float)$sleva;

                // Tričko logika (včetně správného zpracování "Tričko/tílko")
                if (Predmet::jeToTricko($nazev, $typ)) {
                    if ($jeZdarma($castka, $sleva)) {
                        $trickaZdarma++;
                    } elseif ($jeSeSlevou($castka, $sleva)) {
                        $trickaSeSlevou++;
                    } else {
                        $trickaPlacena++;
                    }
                }

                // Tílko logika (ale ne generic "Tričko/tílko" - to počítáme jako tričko)
                if (Predmet::jeToTilko($nazev, $typ) && !Predmet::jeToTricko($nazev, $typ)) {
                    if ($jeZdarma($castka, $sleva)) {
                        $tilkaZdarma++;
                    } elseif ($jeSeSlevou($castka, $sleva)) {
                        $tilkaSeSlevou++;
                    } else {
                        $tilkaPlacena++;
                    }
                }

                // Placky
                if (Predmet::jeToPlacka($nazev)) {
                    if ($castka === 0.0) {
                        $plackyZdarma++;
                    } else {
                        $plackyPlacene++;
                    }
                }

                // Kostky
                if (Predmet::jeToKostka($nazev)) {
                    if ($castka === 0.0) {
                        $kostkyZdarma++;
                    }
                }
            }
        }

        $data = [
            ['Ir-Timestamp', 'Timestamp reportu', date('Y-m-d H:i:s')],
            ['Xr-Tricka-Zaklad', 'Trička placená - kusy', $trickaPlacena],
            ['Xr-Tricka-Sleva', 'Trička se slevou - kusy', $trickaSeSlevou],
            ['Nr-TrickaZdarma', 'Trička zdarma - kusy', $trickaZdarma],
            ['Xr-Tilka-Zaklad', 'Tílka placená - kusy', $tilkaPlacena],
            ['Xr-Tilka-Sleva', 'Tílka se slevou - kusy', $tilkaSeSlevou],
            ['Nr-TilkaZdarma', 'Tílka zdarma - kusy', $tilkaZdarma],
            ['Vr-Placky', 'Placky celkem - kusy', $plackyZdarma + $plackyPlacene],
            ['Ir-Placky-Zdarma', 'Placky zdarma - kusy', $plackyZdarma],
            ['Ir-Kostky-CelkemZdarma', 'Kostky zdarma - kusy', $kostkyZdarma],
        ];

        Report::zPoli(['kod', 'popis', 'data'], $data)->tFormat($format);
    }
}
