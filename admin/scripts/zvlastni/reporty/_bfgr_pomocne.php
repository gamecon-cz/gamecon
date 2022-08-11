<?php
function dejPocetPolozekZdarma(Uzivatel $uzivatel, string $castNazvu) {
    $financniPrehled = $uzivatel->finance()->dejStrukturovanyPrehled();
    $pocetPolozekZdarma = 0;
    foreach ($financniPrehled as $polozka) {
        ['nazev' => $nazev, 'castka' => $castka] = $polozka;
        if ((float)$castka === 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
            $pocetPolozekZdarma++;
        }
    }
    return $pocetPolozekZdarma;
}

function dejPocetPlacekZdarma(Uzivatel $uzivatel): int {
    return dejPocetPolozekZdarma($uzivatel, 'placka');
}

function dejPocetKostekZdarma(Uzivatel $uzivatel): int {
    return dejPocetPolozekZdarma($uzivatel, 'kostka');
}

function dejPocetTricekZdarma(Uzivatel $uzivatel): int {
    return dejPocetPolozekZdarma($uzivatel, 'tričko');
}

function dejPocetTilekZdarma(Uzivatel $uzivatel): int {
    return dejPocetPolozekZdarma($uzivatel, 'tílko');
}

function dejPocetTricekSeSlevou(Uzivatel $uzivatel): int {
    return dejPocetPolozekPlacenych($uzivatel, 'tričko modré') + dejPocetPolozekPlacenych($uzivatel, 'tričko červené');
}

function dejPocetTilekSeSlevou(Uzivatel $uzivatel): int {
    return dejPocetPolozekPlacenych($uzivatel, 'tílko modré') + dejPocetPolozekPlacenych($uzivatel, 'tílko červené');
}

function dejPocetTricekPlacenych(Uzivatel $uzivatel): int {
    return dejPocetPolozekPlacenych($uzivatel, 'tričko') - dejPocetTricekSeSlevou($uzivatel);
}

function dejPocetTilekPlacenych(Uzivatel $uzivatel): int {
    return dejPocetPolozekPlacenych($uzivatel, 'tílko') - dejPocetTilekSeSlevou($uzivatel);
}

function dejPocetPolozekPlacenych(Uzivatel $uzivatel, string $castNazvu) {
    $financniPrehled = $uzivatel->finance()->dejStrukturovanyPrehled();
    $pocetPolozekPlacenych = 0;
    foreach ($financniPrehled as $polozka) {
        ['nazev' => $nazev, 'castka' => $castka] = $polozka;
        if ((float)$castka > 0.0 && mb_stripos($nazev, $castNazvu) !== false) {
            $pocetPolozekPlacenych++;
        }
    }
    return $pocetPolozekPlacenych;
}

function dejPocetPlacekPlacenych(Uzivatel $uzivatel): int {
    return dejPocetPolozekPlacenych($uzivatel, 'placka');
}

function dejNazvyAPoctyJidel(Uzivatel $uzivatel, array $moznaJidla): array {
    $objednanaJidla = dejNazvyAPoctyPredmetu($uzivatel, implode('|', \Gamecon\Jidlo::dejJidlaBehemDne()));
    uksort($objednanaJidla, static function (string $nejakeJidloADen, string $jineJidloADen) {
        $nejakeJidloBehemDne = najdiJidloBehemDne($nejakeJidloADen);
        $jineJidloBehemDne = najdiJidloBehemDne($jineJidloADen);
        $rozdilPoradiJidel = \Gamecon\Jidlo::dejPoradiJidlaBehemDne($nejakeJidloBehemDne) <=> \Gamecon\Jidlo::dejPoradiJidlaBehemDne($jineJidloBehemDne);
        if ($rozdilPoradiJidel !== 0) {
            return $rozdilPoradiJidel; // nejdříve chceme řadit podle typu jídla, teprve potom podle dnů
        }
        $denNejakehoJidla = najdiDenVTydnu($nejakeJidloADen);
        $denJinehoJidla = najdiDenVTydnu($jineJidloADen);
        return \Gamecon\Cas\DateTimeCz::poradiDne($denNejakehoJidla) <=> \Gamecon\Cas\DateTimeCz::poradiDne($denJinehoJidla);
    });
    $vsechnaJidlaJakoNeobjednana = array_fill_keys($moznaJidla, 0);
    return array_merge($vsechnaJidlaJakoNeobjednana, $objednanaJidla);
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
 * @param Uzivatel $uzivatel
 * @param string|array $castNazvuRegexpNeboPole
 * @return array
 */
function dejNazvyAPoctyPredmetu(Uzivatel $uzivatel, $castNazvuRegexpNeboPole): array {
    $castNazvuRegexp = is_array($castNazvuRegexpNeboPole)
        ? dejPoleJakoRegexp($castNazvuRegexpNeboPole, '~')
        : $castNazvuRegexpNeboPole;
    $financniPrehled = $uzivatel->finance()->dejStrukturovanyPrehled();
    $poctyPredmetu = [];
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

function dejNazvyAPoctyCovidTestu(Uzivatel $uzivatel, array $vsechnyMozneCovidTesty): array {
    $objednaneCovidTesty = dejNazvyAPoctyPredmetu($uzivatel, 'covid');
    return seradADoplnNenakoupene($objednaneCovidTesty, $vsechnyMozneCovidTesty);
}

function dejNazvyAPoctyOstatnichPredmetu(Uzivatel $uzivatel, array $vsechnyMozneOstatniPredmety): array {
    $objednaneOstatniPredmety = dejNazvyAPoctyPredmetu($uzivatel, $vsechnyMozneOstatniPredmety);
    return seradADoplnNenakoupene($objednaneOstatniPredmety, $vsechnyMozneOstatniPredmety);
}

function seradADoplnNenakoupene(array $objednaneSPocty, array $vsechnyMozneJenNazvy): array {
    $vsechnyMozneJakoNeobjednane = array_fill_keys($vsechnyMozneJenNazvy, 0); // zachová pořadí
    $objednaneANeobjednane = array_merge( // zachová pořadí
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

function dejNazvyAPoctyKostek(Uzivatel $uzivatel, array $vsechnyMozneKostky): array {
    $objednaneKostky = dejNazvyAPoctyPredmetu($uzivatel, 'kostka');
    foreach ($objednaneKostky as $objednanaKostka => $pocet) {
        if (!preg_match('~ \d{4}$~', $objednanaKostka)) {
            unset($objednaneKostky[$objednanaKostka]);
            $objednaneKostky[$objednanaKostka . ' ' . ROK] = $pocet;
        }
    }
    return seradADoplnNenakoupene($objednaneKostky, $vsechnyMozneKostky);
}
