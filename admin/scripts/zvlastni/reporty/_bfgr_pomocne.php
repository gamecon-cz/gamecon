<?php
function dejPocetPolozekZdarma(Uzivatel $navstevnik, string $castNazvu) {
    $financniPrehled    = $navstevnik->finance()->dejStrukturovanyPrehled();
    $pocetPolozekZdarma = 0;
    foreach ($financniPrehled as $polozka) {
        ['nazev' => $nazev, 'castka' => $castka] = $polozka;
        if ((float)$castka === 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
            $pocetPolozekZdarma++;
        }
    }
    return $pocetPolozekZdarma;
}

function dejPocetPlacekZdarma(Uzivatel $navstevnik): int {
    return dejPocetPolozekZdarma($navstevnik, 'placka');
}

function dejPocetKostekZdarma(Uzivatel $navstevnik): int {
    return dejPocetPolozekZdarma($navstevnik, 'kostka');
}

function dejPocetTricekZdarma(Uzivatel $navstevnik): int {
    return dejPocetPolozekZdarma($navstevnik, 'tričko');
}

function dejPocetTilekZdarma(Uzivatel $navstevnik): int {
    return dejPocetPolozekZdarma($navstevnik, 'tílko');
}

function dejPocetTricekSeSlevou(Uzivatel $navstevnik): int {
    return dejPocetPolozekPlacenych($navstevnik, 'tričko modré') + dejPocetPolozekPlacenych($navstevnik, 'tričko červené');
}

function dejPocetTilekSeSlevou(Uzivatel $navstevnik): int {
    return dejPocetPolozekPlacenych($navstevnik, 'tílko modré') + dejPocetPolozekPlacenych($navstevnik, 'tílko červené');
}

function dejPocetTricekPlacenych(Uzivatel $navstevnik): int {
    return dejPocetPolozekPlacenych($navstevnik, 'tričko') - dejPocetTricekSeSlevou($navstevnik);
}

function dejPocetTilekPlacenych(Uzivatel $navstevnik): int {
    return dejPocetPolozekPlacenych($navstevnik, 'tílko') - dejPocetTilekSeSlevou($navstevnik);
}

function dejPocetPolozekPlacenych(Uzivatel $navstevnik, string $castNazvu) {
    $financniPrehled       = $navstevnik->finance()->dejStrukturovanyPrehled();
    $pocetPolozekPlacenych = 0;
    foreach ($financniPrehled as $polozka) {
        ['nazev' => $nazev, 'castka' => $castka] = $polozka;
        if ((float)$castka > 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
            $pocetPolozekPlacenych++;
        }
    }
    return $pocetPolozekPlacenych;
}

function dejPocetPlacekPlacenych(Uzivatel $navstevnik): int {
    return dejPocetPolozekPlacenych($navstevnik, 'placka');
}

function dejNazvyAPoctyJidel(Uzivatel $navstevnik, array $moznaJidla): array {
    $objednanaJidla = dejNazvyAPoctyPredmetu($navstevnik, implode('|', \Gamecon\Jidlo::dejJidlaBehemDne()));
    uksort($objednanaJidla, static function (string $nejakeJidloADen, string $jineJidloADen) {
        $nejakeJidloBehemDne = najdiJidloBehemDne($nejakeJidloADen);
        $jineJidloBehemDne   = najdiJidloBehemDne($jineJidloADen);
        $rozdilPoradiJidel   = \Gamecon\Jidlo::dejPoradiJidlaBehemDne($nejakeJidloBehemDne) <=> \Gamecon\Jidlo::dejPoradiJidlaBehemDne($jineJidloBehemDne);
        if ($rozdilPoradiJidel !== 0) {
            return $rozdilPoradiJidel; // nejdříve chceme řadit podle typu jídla, teprve potom podle dnů
        }
        $denNejakehoJidla = najdiDenVTydnu($nejakeJidloADen);
        $denJinehoJidla   = najdiDenVTydnu($jineJidloADen);
        return \Gamecon\Cas\DateTimeCz::poradiDne($denNejakehoJidla) <=> \Gamecon\Cas\DateTimeCz::poradiDne($denJinehoJidla);
    });
    $vsechnaJidlaJakoNeobjednana = array_fill_keys($moznaJidla, 0);
    $vsechnaJidla                = array_merge($vsechnaJidlaJakoNeobjednana, $objednanaJidla);
    return pridejNaZacatekPole('Celkem jídel', array_sum($vsechnaJidla), $vsechnaJidla);
}

function najdiDenVTydnu(string $text): string {
    preg_match('~' . implode('|', \Gamecon\Cas\DateTimeCz::dejDnyVTydnu()) . '~uiS', $text, $matches);
    return $matches[0];
}

function najdiJidloBehemDne(string $text): string {
    preg_match('~' . implode('|', \Gamecon\Jidlo::dejJidlaBehemDne()) . '~uiS', $text, $matches);
    return $matches[0];
}

/**
 * @param Uzivatel $navstevnik
 * @param string|array $castNazvuRegexpNeboPole
 * @return array
 */
function dejNazvyAPoctyPredmetu(Uzivatel $navstevnik, $castNazvuRegexpNeboPole): array {
    $castNazvuRegexp = is_array($castNazvuRegexpNeboPole)
        ? dejPoleJakoRegexp($castNazvuRegexpNeboPole, '~')
        : $castNazvuRegexpNeboPole;
    $financniPrehled = $navstevnik->finance()->dejStrukturovanyPrehled();
    $poctyPredmetu   = [];
    foreach ($financniPrehled as $polozka) {
        ['nazev' => $nazev, 'pocet' => $pocet] = $polozka;
        if (preg_match('~' . $castNazvuRegexp . '~iS', $nazev)) {
            $poctyPredmetu[$nazev] = ($poctyPredmetu[$nazev] ?? 0) + $pocet;
        }
    }
    return $poctyPredmetu;
}

function dejPoleJakoRegexp(array $retezce, string $delimiter) {
    return implode(
        '|',
        array_map(
            static function (string $retezec) use ($delimiter) {
                return preg_quote($retezec, $delimiter);
            },
            $retezce)
    );
}

function dejNazvyAPoctyCovidTestu(Uzivatel $navstevnik, array $vsechnyMozneCovidTesty): array {
    $objednaneCovidTesty = dejNazvyAPoctyPredmetu($navstevnik, 'covid');
    return seradADoplnNenakoupene($objednaneCovidTesty, $vsechnyMozneCovidTesty);
}

function dejNazvyAPoctySvrsku(Uzivatel $navstevnik): array {
    $poctySvrsku = [
        'Tričko zdarma'             => dejPocetTricekZdarma($navstevnik),
        'Tílko zdarma'              => dejPocetTilekZdarma($navstevnik),
        'Tričko se slevou'          => dejPocetTricekSeSlevou($navstevnik),
        'Tílko se slevou'           => dejPocetTilekSeSlevou($navstevnik),
        'Účastnické tričko placené' => dejPocetTricekPlacenych($navstevnik),
        'Účastnické tílko placené'  => dejPocetTilekPlacenych($navstevnik),
    ];
    return pridejNaZacatekPole('Celkem svršků', array_sum($poctySvrsku), $poctySvrsku);
}

function dejNazvyAPoctyOstatnichPredmetu(Uzivatel $navstevnik, array $vsechnyMozneOstatniPredmety): array {
    $objednaneOstatniPredmety = dejNazvyAPoctyPredmetu($navstevnik, $vsechnyMozneOstatniPredmety);
    return seradADoplnNenakoupene($objednaneOstatniPredmety, $vsechnyMozneOstatniPredmety);
}

function seradADoplnNenakoupene(array $objednaneSPocty, array $vsechnyMozneJenNazvy): array {
    $vsechnyMozneJakoNeobjednane = array_fill_keys($vsechnyMozneJenNazvy, 0); // zachová pořadí
    $objednaneANeobjednane       = array_merge( // zachová pořadí
        $vsechnyMozneJakoNeobjednane,
        $objednaneSPocty
    );
    if (count($objednaneANeobjednane) !== count($vsechnyMozneJenNazvy)) {
        throw new \RuntimeException(
            'Neznámé položky ' . implode(array_keys(array_diff_key($objednaneSPocty, $vsechnyMozneJakoNeobjednane)))
        );
    }
    return $objednaneANeobjednane;
}

function dejNazvyAPoctyPlacek(Uzivatel $navstevnik): array {
    $poctyPlacek = [
        'Placka zdarma'     => dejPocetPlacekZdarma($navstevnik),
        'Placka GC placená' => dejPocetPlacekPlacenych($navstevnik),
    ];
    return pridejNaZacatekPole('Celkem placek', array_sum($poctyPlacek), $poctyPlacek);
}

function dejNazvyAPoctyKostek(Uzivatel $navstevnik, array $vsechnyMozneKostky): array {
    $objednaneKostky = dejNazvyAPoctyPredmetu($navstevnik, 'kostka');
    foreach ($objednaneKostky as $objednanaKostka => $pocet) {
        if (!preg_match('~ \d{4}$~', $objednanaKostka)) {
            unset($objednaneKostky[$objednanaKostka]);
            $objednaneKostky[$objednanaKostka . ' ' . ROK] = $pocet;
        }
    }
    $poctyKostek = seradADoplnNenakoupene($objednaneKostky, $vsechnyMozneKostky);
    // pozor, kostky zdarma je počet kostek z výše uvedených objednaných (podmnožina) - nejsou to kostky navíc
    $poctyKostek['Kostka zdarma'] = dejPocetKostekZdarma($navstevnik);
    return pridejNaZacatekPole('Celkem kostek', array_sum($objednaneKostky), $poctyKostek);
}
