<?php
// takzvaný BFGR (Big f*king Gandalf report)

use Gamecon\Cas\DateTimeCz;
use Gamecon\Zidle;
use Gamecon\Shop\Shop;

require __DIR__ . '/sdilene-hlavicky.php';

function ed($datum) { // excel datum
    if (!$datum) {
        return null;
    }
    return date('j.n.Y G:i', strtotime($datum));
}

function ec($cislo) { // excel číslo
    return str_replace('.', ',', $cislo);
}

function ut($typ) { // ubytování typ - z názvu předmětu odhadne typ
    return preg_replace('@ ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle) ?@iu', '', $typ);
}

// poradi je dulezite, udava prioritu
$idZidliProPozici = [
    Zidle::ORGANIZATOR,
    Zidle::ORGANIZATOR_S_BONUSY_1,
    Zidle::ORGANIZATOR_S_BONUSY_2,
    Zidle::VYPRAVEC,
    Zidle::PARTNER,
    Zidle::DOBROVOLNIK_SENIOR,
];
$jmenaZidliProPozici = [];
foreach ($idZidliProPozici as $idZidle) {
    $jmenaZidliProPozici[$idZidle] = Zidle::zId($idZidle)->jmenoZidle();
}
$dejNazevPozice = static function (array $idPrav) use ($jmenaZidliProPozici): string {
    foreach ($jmenaZidliProPozici as $idZidle => $jmenoZidle) {
        if (in_array($idZidle, $idPrav, false)) {
            return $jmenoZidle;
        }
    }
    return 'Účastník';
};

$ucastPodleRoku = [];
$maxRok = po(REG_GC_DO) ? ROK : ROK - 1;
for ($i = 2009; $i <= $maxRok; $i++) {
    $ucastPodleRoku[$i] = 'účast ' . $i;
}

$letosniPlacky = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%placka%' COLLATE utf8_czech_ci
AND stav > 0
SQL, [ROK]
);

$letosniKostky = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%kostka%' COLLATE utf8_czech_ci
AND stav > 0
SQL, [ROK]
);

$letosniJidla = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, TRIM(shop_predmety.nazev)
FROM shop_predmety
WHERE shop_predmety.typ = $1
AND model_rok = $2
ORDER BY FIELD(SUBSTRING(TRIM(shop_predmety.nazev), 1, POSITION(' ' IN TRIM(shop_predmety.nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře'),
         FIELD(SUBSTRING(TRIM(shop_predmety.nazev), POSITION(' ' IN TRIM(shop_predmety.nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle')
SQL, [Shop::JIDLO, ROK]
);

$letosniTricka = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%tričko%' COLLATE utf8_czech_ci
AND stav > 0
SQL
);

$letosniTilka = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%tílko%' COLLATE utf8_czech_ci
AND stav > 0
SQL
);

$letosniOstatniPredmety = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, TRIM(shop_predmety.nazev)
FROM shop_predmety
WHERE shop_predmety.typ = $1
AND stav > 0
AND (TRIM(nazev) IN ('GameCon blok', 'Nicknack') OR nazev LIKE '%ponožky%' COLLATE utf8_czech_ci)
ORDER BY TRIM(shop_predmety.nazev)
SQL, [Shop::PREDMET]
);

$hlavicka = array_merge(
    ['Účastník' => ['ID', 'Příjmení', 'Jméno', 'Přezdívka', 'Mail', 'Židle', 'Práva', 'Datum registrace', 'Prošel infopultem', 'Odjel kdy']],
    ['Datum narození' => ['Den', 'Měsíc', 'Rok']],
    ['Bydliště' => ['Stát', 'Město', 'Ulice', 'PSČ', 'Škola']],
    ['Ubytovací informace' => array_merge(['Chci bydlet s', 'První noc', 'Poslední noc (počátek)', 'Typ', 'Dorazil na GC'], $ucastPodleRoku)],
    ['Celkové náklady' => ['Celkem dní', 'Cena / den', 'Ubytování', 'Předměty a strava']],
    ['Ostatní platby' => ['Aktivity', 'Bonus za vedení aktivit', 'Využitý bonus za vedení aktivit', 'Proplacený bonus za vedení aktivit', 'dobrovolné vstupné', 'dobrovolné vstupné (pozdě)', 'stav', 'suma slev', 'zůstatek z minula', 'připsané platby', 'první blok', 'poslední blok', 'dobrovolník pozice', 'dobrovolník info', 'Dárky a zlevněné nákupy', 'Objednávky', 'Poznámka']],
    ['Eshop' => array_merge(['sleva', 'placka zdarma', 'placka GC placená', 'kostka zdarma'], $letosniKostky, $letosniJidla, ['tričko zdarma', 'tílko zdarma', 'tričko se slevou', 'tílko se slevou', 'účastnické tričko placené', 'účastnické tílko placené'], $letosniOstatniPredmety)],
);

$sqlNaPocetJednohoPredmetu = static function (int $idPredmetu): string {
    $rok = ROK;
    return <<<SQL
 COALESCE((SELECT COUNT(shop_predmety.id_predmetu) FROM shop_nakupy
     JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok={$rok} AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.id_predmetu = {$idPredmetu} GROUP BY shop_predmety.id_predmetu), 0)
SQL;
};
$sqlSPoctemPlacek = (static function () use ($letosniPlacky, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniPlacky as $idPlacky => $nazevPlacky) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idPlacky)}) AS `$nazevPlacky`";
    }
    return implode(',', $sqlCasti);
})();
$sqlSPoctemKostek = (static function () use ($letosniKostky, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniKostky as $idKostky => $nazevKostky) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idKostky)}) AS `$nazevKostky`";
    }
    return implode(',', $sqlCasti);
})();
$sqlSPoctemJidel = (static function () use ($letosniJidla, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniJidla as $idJidla => $nazevJidla) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idJidla)}) AS `$nazevJidla`";
    }
    return implode(',', $sqlCasti);
})();
$sqlSPoctemTricek = (static function () use ($letosniTricka, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniTricka as $idTricka => $nazevTricka) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idTricka)}) AS `$nazevTricka`";
    }
    return implode(',', $sqlCasti);
})();
$sqlSPoctemTilek = (static function () use ($letosniTilka, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniTilka as $idTilka => $nazevTilka) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idTilka)}) AS `$nazevTilka`";
    }
    return implode(',', $sqlCasti);
})();
$sqlSPoctemOstatnichPredmetu = (static function () use ($letosniOstatniPredmety, $sqlNaPocetJednohoPredmetu): string {
    $sqlCasti = [];
    foreach ($letosniOstatniPredmety as $idPredmetu => $nazevPredmetu) {
        $sqlCasti[] = "({$sqlNaPocetJednohoPredmetu((int)$idPredmetu)}) AS `$nazevPredmetu`";
    }
    return implode(',', $sqlCasti);
})();

$rok = ROK;
$o = dbQuery(<<<SQL
SELECT
    uzivatele_hodnoty.*,
    prihlasen.posazen AS prihlasen_na_gc_kdy,
    pritomen.posazen as prosel_info_kdy,
    odjel.posazen as odjel_kdy,
    ( SELECT MIN(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=2 ) AS den_prvni,
    ( SELECT MAX(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=2 ) AS den_posledni,
    ( SELECT GROUP_CONCAT(shop_predmety.nazev SEPARATOR ', ') FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=2 ) AS ubytovani_typ,
    $sqlSPoctemPlacek,
    $sqlSPoctemKostek,
    $sqlSPoctemJidel,
    $sqlSPoctemTricek,
    $sqlSPoctemTilek,
    $sqlSPoctemOstatnichPredmetu,
    ( SELECT GROUP_CONCAT(r_prava_soupis.jmeno_prava SEPARATOR ', ')
      FROM r_uzivatele_zidle
      JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle=r_prava_zidle.id_zidle
      JOIN r_prava_soupis ON r_prava_soupis.id_prava=r_prava_zidle.id_prava
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as pravaZDotazu,
    ( SELECT GROUP_CONCAT(r_uzivatele_zidle.id_zidle SEPARATOR ',')
      FROM r_uzivatele_zidle
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as idPravZDotazu,
    ( SELECT GROUP_CONCAT(r_zidle_soupis.jmeno_zidle SEPARATOR ', ')
      FROM r_uzivatele_zidle
      LEFT JOIN r_zidle_soupis ON r_uzivatele_zidle.id_zidle = r_zidle_soupis.id_zidle
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as zidleZDotazu,
FROM uzivatele_hodnoty
LEFT JOIN r_uzivatele_zidle AS prihlasen ON(prihlasen.id_zidle = $0 AND prihlasen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN r_uzivatele_zidle AS pritomen ON(pritomen.id_zidle = $1 AND pritomen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN r_uzivatele_zidle AS odjel ON(odjel.id_zidle = $2 AND odjel.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
WHERE prihlasen.id_uzivatele IS NOT NULL -- left join, takže může být NULL ve smyslu "nemáme záznam" = "není přihlášen"
    OR pritomen.id_uzivatele IS NOT NULL -- tohle by bylo hodně divné, musela by být díra v systému, aby nebyl přihlášen ale byl přítomen, ale radši...
    OR EXISTS(SELECT * FROM shop_nakupy WHERE uzivatele_hodnoty.id_uzivatele = shop_nakupy.id_uzivatele AND shop_nakupy.rok = $rok)
    OR EXISTS(SELECT * FROM platby WHERE platby.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platby.rok = $rok)
SQL,
    [0 => \Gamecon\Zidle::PRIHLASEN_NA_LETOSNI_GC, 1 => \Gamecon\Zidle::PRITOMEN_NA_LETOSNIM_GC, 2 => \Gamecon\Zidle::ODJEL_Z_LETOSNIHO_GC]
);
if (mysqli_num_rows($o) === 0) {
    exit('V tabulce nejsou žádná data.');
}

$hlavniHlavicka = [];
$obsah = [0 => []];
foreach ($hlavicka as $hlavni => $vedlejsiHlavicka) {
    $hlavniHlavicka[] = $hlavni;
    for ($vypln = 0, $celkemVyplne = count($vedlejsiHlavicka) - 1; $vypln < $celkemVyplne; $vypln++) {
        $hlavniHlavicka[] = '';
    }
    foreach ($vedlejsiHlavicka as $vedlejsi) {
        $obsah[0][] = $vedlejsi; // pod-hlavicka je prvnim radkem obsahu
    }
}

$letosniPlackyKlice = array_fill_keys($letosniPlacky, null);
$letosniKostkyKlice = array_fill_keys($letosniKostky, null);
$letosniJidlaKlice = array_fill_keys($letosniJidla, null);
$letosniTrickaKlice = array_fill_keys($letosniTricka, null);
$letosniTilkaKlice = array_fill_keys($letosniTilka, null);
$letosniOstatniPredmetyKlice = array_fill_keys($letosniOstatniPredmety, null);

while ($r = mysqli_fetch_assoc($o)) {
    $un = new Uzivatel($r);
    $un->nactiPrava(); //sql subdotaz, zlo
    $finance = $un->finance();
    $cenik = new Cenik($un, $finance->bonusZaVedeniAktivit());
    $ucastiHistorie = [];
    foreach ($ucastPodleRoku as $rok => $nul) {
        $ucastiHistorie[] = $un->maPravo((int)('-' . substr($rok, 2) . '02')) ? 'ano' : 'ne';
    }
    $stat = '';
    try {
        $stat = $un->stat();
    } catch (Exception $e) {
    }
    $letosniPlackyPocty = array_intersect_key($r, $letosniPlackyKlice);
    $pocetLetosnichPlacek = (int)array_sum($letosniPlackyPocty);
    $pocetLetosnichPlacekZdarma = min($pocetLetosnichPlacek, $finance->maximalniPocetPlacekZdarma());
    $pocetLetosnichPlacekPlacenych = $pocetLetosnichPlacek - $pocetLetosnichPlacekZdarma;

    $letosniKostkyPocty = array_intersect_key($r, $letosniKostkyKlice);
    $pocetLetosnichKostek = (int)array_sum($letosniKostkyPocty);
    $pocetLetosnichKostekZdarma = min($pocetLetosnichKostek, $finance->maximalniPocetKostekZdarma());

    $letosniTrickaPocty = array_intersect_key($r, $letosniTrickaKlice);
    $pocetLetosnichTricek = (int)array_sum($letosniTrickaPocty);
    $letosniModraTrickaPocty = array_filter($letosniTrickaPocty, static function (string $nazevTricka) {
        return mb_stripos($nazevTricka, 'modré');
    }, ARRAY_FILTER_USE_KEY);
    $pocetLetosnichModrychTricek = (int)array_sum($letosniModraTrickaPocty);
    // tech co jsou zdarma jen kvuli specialnimu pravu na modre tricko zdarma
    $pocetLetosnichModrychTricekZdarma = min($pocetLetosnichModrychTricek, $finance->maximalniPocetModrychTricekZdarma());
    // mohou to byt i modra tricka, ale bez tech, co byly zdarma kvuli specialnimu pravu na modre tricko
    $pocetLetosnichTricekAleBezModrychZdarma = (int)array_sum($letosniTrickaPocty) - $pocetLetosnichModrychTricekZdarma;
    $pocetLetosnichTricekZdarma = min($pocetLetosnichTricekAleBezModrychZdarma, $finance->maximalniPocetLibovolnychTricekZdarma()) + $pocetLetosnichModrychTricekZdarma;
    $pocetLetosnichModrychTricekSeSlevou = $finance->muzeObjednavatModreTrickoSeSlevou()
        ? $pocetLetosnichModrychTricek - $pocetLetosnichModrychTricekZdarma
        : 0;
    $letosniCervenaTrickaPocty = array_filter($letosniTrickaPocty, static function (string $nazevTricka) {
        return mb_stripos($nazevTricka, 'červené');
    }, ARRAY_FILTER_USE_KEY);
    $pocetLetosnichCervenychTricek = (int)array_sum($letosniCervenaTrickaPocty);
    $pocetLetosnichCervenychTricekSeSlevou = $finance->muzeObjednavatCerveneTrickoSeSlevou()
        ? $pocetLetosnichCervenychTricek
        : 0;
    $pocetLetosnichTricekSeSlevou = $pocetLetosnichModrychTricekSeSlevou + $pocetLetosnichCervenychTricekSeSlevou;
    $pocetLetosnichTricekPlacenych = $pocetLetosnichTricek - $pocetLetosnichTricekZdarma - $pocetLetosnichModrychTricekSeSlevou;

    // POZOR, tady predpokladame, ze kdo si kupuje tilka, nekupuje si tricka - pokud jo, tak maximalniPocetLibovolnychTricekZdarma() tu pouzivame blbe, protoze zdojnasobujeme maximum
    $letosniTilkaPocty = array_intersect_key($r, $letosniTilkaKlice);
    $pocetLetosnichTilek = (int)array_sum($letosniTilkaPocty);
    $letosniModraTilkaPocty = array_filter($letosniTilkaPocty, static function (string $nazevTilka) {
        return mb_stripos($nazevTilka, 'modré');
    }, ARRAY_FILTER_USE_KEY);
    $pocetLetosnichModrychTilek = (int)array_sum($letosniModraTilkaPocty);
    // tech co jsou zdarma jen kvuli specialnimu pravu na modre tricko zdarma
    $pocetLetosnichModrychTilekZdarma = min($pocetLetosnichModrychTilek, $finance->maximalniPocetModrychTricekZdarma());
    // mohou to byt i modra tricka, ale bez tech, co byly zdarma kvuli specialnimu pravu na modre tricko
    $pocetLetosnichTilekBezModrychZdarma = (int)array_sum($letosniTilkaPocty) - $pocetLetosnichModrychTilekZdarma;
    $pocetLetosnichTilekZdarma = min($pocetLetosnichTilekBezModrychZdarma, $finance->maximalniPocetLibovolnychTricekZdarma()) + $pocetLetosnichModrychTilekZdarma;
    $pocetLetosnichModrychTilekSeSlevou = $finance->muzeObjednavatModreTrickoSeSlevou()
        ? $pocetLetosnichModrychTilek - $pocetLetosnichModrychTilekZdarma
        : 0;
    $letosniCervenaTilkaPocty = array_filter($letosniTilkaPocty, static function (string $nazevTilka) {
        return mb_stripos($nazevTilka, 'červené');
    }, ARRAY_FILTER_USE_KEY);
    $pocetLetosnichCervenychTilek = (int)array_sum($letosniCervenaTilkaPocty);
    $pocetLetosnichCervenychTilekSeSlevou = $finance->muzeObjednavatCerveneTrickoSeSlevou()
        ? $pocetLetosnichCervenychTilek
        : 0;
    $pocetLetosnichTilekSeSlevou = $pocetLetosnichModrychTilekSeSlevou + $pocetLetosnichCervenychTilekSeSlevou;
    $pocetLetosnichTilekPlacenych = $pocetLetosnichTilek - $pocetLetosnichTilekZdarma;

    $letosniJidlaPocty = array_intersect_key($r, $letosniJidlaKlice);

    $letosniOstatniPredmetyPocty = array_intersect_key($r, $letosniOstatniPredmetyKlice);

    $obsah[] = array_merge(
        [
            $r['id_uzivatele'], // 'ID'
            $r['prijmeni_uzivatele'], // 'Příjmení'
            $r['jmeno_uzivatele'], // 'Jméno', 'Přezdívka', 'Mail', 'Židle', 'Práva', 'Datum registrace', 'Prošel infopultem
            $r['login_uzivatele'], // 'Přezdívka'
            $r['email1_uzivatele'], // 'Mail'
            $r['zidleZDotazu'], // 'Židle'
            nahradNazvyKonstantZaHodnoty($r['pravaZDotazu'] ?? ''), // 'Práva'
            $dejNazevPozice(explode(',', $r['idPravZDotazu'])),
            ed($r['prihlasen_na_gc_kdy']), // 'Datum registrace'
            ed($r['prosel_info_kdy']), // 'Prošel infopultem
            ed($r['odjel_kdy']), // 'Odjel kdy'
            date('j', strtotime($r['datum_narozeni'])),
            date('n', strtotime($r['datum_narozeni'])),
            date('Y', strtotime($r['datum_narozeni'])),
            $stat,
            $r['mesto_uzivatele'],
            $r['ulice_a_cp_uzivatele'],
            $r['psc_uzivatele'],
            $r['skola'],
            $r['ubytovan_s'],
            $r['den_prvni'] === null ? '-' :
                (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add("P$r[den_prvni]D")->format('j.n.Y'),
            $r['den_posledni'] === null ? '-' :
                (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add("P$r[den_posledni]D")->format('j.n.Y'),
            ut($r['ubytovani_typ']),
            $un->gcPritomen() ? 'ano' : 'ne',
        ],
        $ucastiHistorie,
        [
            $pobyt = ($r['den_prvni'] !== null ? $r['den_posledni'] - $r['den_prvni'] + 1 : 0),
            $pobyt ? $finance->cenaUbytovani() / $pobyt : 0,
            $finance->cenaUbytovani(),
            $finance->cenaPredmety(),
            $finance->cenaAktivity(),

            $finance->bonusZaVedeniAktivit(),
            $finance->vyuzityBonusZaAktivity(),
            $finance->proplacenyBonusZaAktivity(),

            $finance->vstupne(),
            $finance->vstupnePozde(),
            ec($finance->stav()),
            ec($finance->slevaObecna()),  // Suma slev
            ec($r['zustatek']),
            ec($finance->sumaPlateb()), // připsané platby
            ed($un->prvniBlok()),
            ed($un->posledniBlok()),
            $r['pomoc_typ'], // dobrovolník pozice
            $r['pomoc_vice'], // dobrovolník info
            implode(", ", array_merge($finance->slevyVse(), $finance->slevyAktivity())), // Dárky a zlevněné nákupy
            $finance->prehledPopis(), // Objednávky
            strip_tags($r['poznamka'] ?? ''),
        ],
        [
            $finance->slevaZaAktivityVProcentech(), // sleva
            $pocetLetosnichPlacekZdarma, // placka zdarma
            $pocetLetosnichPlacekPlacenych, // placka GC placená
            $pocetLetosnichKostekZdarma, // kostka zdarma
        ],
        $letosniKostkyPocty,
        $letosniJidlaPocty,
        [
            $pocetLetosnichTricekZdarma, // tričko zdarma
            $pocetLetosnichTilekZdarma, // tílko zdarma
            $pocetLetosnichTricekSeSlevou, // tričko se slevou
            $pocetLetosnichTilekSeSlevou, // tílko se slevou
            $pocetLetosnichTricekPlacenych, // účastnické tričko placené
            $pocetLetosnichTilekPlacenych, // účastnické tílko placené
        ],
        $letosniOstatniPredmetyPocty,
    );
}

Report::zPoli($hlavniHlavicka, $obsah)->tFormat(get('format'), null, 0);
