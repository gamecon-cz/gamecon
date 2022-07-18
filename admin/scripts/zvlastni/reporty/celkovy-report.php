<?php
// takzvaný BFGR (Big f*king Gandalf report)

use \Gamecon\Cas\DateTimeCz;

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

$ucastPodleRoku = [];
$maxRok = po(REG_GC_DO) ? ROK : ROK - 1;
for ($i = 2009; $i <= $maxRok; $i++) {
    $ucastPodleRoku[$i] = 'účast ' . $i;
}

$hlavicka = array_merge(
    ['Účastník' => ['ID', 'Příjmení', 'Jméno', 'Přezdívka', 'Mail', 'Židle', 'Práva', 'Datum registrace', 'Prošel infopultem', 'Odjel kdy']],
    ['Datum narození' => ['Den', 'Měsíc', 'Rok']],
    ['Bydliště' => ['Stát', 'Město', 'Ulice', 'PSČ', 'Škola']],
    ['Ubytovací informace' => array_merge(['Chci bydlet s', 'První noc', 'Poslední noc (počátek)', 'Typ', 'Dorazil na GC'], $ucastPodleRoku)],
    ['Celkové náklady' => ['Celkem dní', 'Cena / den', 'Ubytování', 'Předměty a strava']],
    ['Ostatní platby' => ['Aktivity', 'Bonus za vedení aktivit', 'Využitý bonus za vedení aktivit', 'Proplacený bonus za vedení aktivit', 'dobrovolné vstupné', 'dobrovolné vstupné (pozdě)', 'stav', 'suma slev', 'zůstatek z minula', 'připsané platby', 'první blok', 'poslední blok', 'dobrovolník pozice', 'dobrovolník info', 'Dárky a zlevněné nákupy', 'Objednávky', 'Poznámka']]
);
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
    ( SELECT GROUP_CONCAT(r_prava_soupis.jmeno_prava SEPARATOR ', ')
      FROM r_uzivatele_zidle
      JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle=r_prava_zidle.id_zidle
      JOIN r_prava_soupis ON r_prava_soupis.id_prava=r_prava_zidle.id_prava
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as pravaZDotazu,
    ( SELECT GROUP_CONCAT(r_zidle_soupis.jmeno_zidle SEPARATOR ', ')
      FROM r_uzivatele_zidle
      LEFT JOIN r_zidle_soupis ON r_uzivatele_zidle.id_zidle = r_zidle_soupis.id_zidle
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as zidleZDotazu
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
while ($r = mysqli_fetch_assoc($o)) {
    $un = new Uzivatel($r);
    $un->nactiPrava(); //sql subdotaz, zlo
    $f = $un->finance();
    $ucastiHistorie = [];
    foreach ($ucastPodleRoku as $rok => $nul)
        $ucastiHistorie[] = $un->maPravo((int)('-' . substr($rok, 2) . '02')) ? 'ano' : 'ne';
    $stat = '';
    try {
        $stat = $un->stat();
    } catch (Exception $e) {
    }
    $obsah[] = array_merge(
        [
            $r['id_uzivatele'], // 'ID'
            $r['prijmeni_uzivatele'], // 'Příjmení'
            $r['jmeno_uzivatele'], // 'Jméno', 'Přezdívka', 'Mail', 'Židle', 'Práva', 'Datum registrace', 'Prošel infopultem
            $r['login_uzivatele'], // 'Přezdívka'
            $r['email1_uzivatele'], // 'Mail'
            $r['zidleZDotazu'], // 'Židle'
            nahradNazvyKonstantZaHodnoty($r['pravaZDotazu'] ?? ''), // 'Práva'
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
            $pobyt ? $f->cenaUbytovani() / $pobyt : 0,
            $f->cenaUbytovani(),
            $f->cenaPredmety(),
            $f->cenaAktivity(),

            $f->bonusZaVedeniAktivit(),
            $f->vyuzityBonusZaAktivity(),
            $f->proplacenyBonusZaAktivity(),

            $f->vstupne(),
            $f->vstupnePozde(),
            ec($f->stav()),
            ec($f->slevaObecna()),  // Suma slev
            ec($r['zustatek']),
            ec($f->sumaPlateb()), // připsané platby
            ed($un->prvniBlok()),
            ed($un->posledniBlok()),
            $r['pomoc_typ'], // dobrovolník pozice
            $r['pomoc_vice'], // dobrovolník info
            implode(", ", array_merge($f->slevyVse(), $f->slevyAktivity())), // Dárky a zlevněné nákupy
            $f->prehledPopis(), // Objednávky
            strip_tags($r['poznamka'] ?? ''),
        ]
    );
}

Report::zPoli($hlavniHlavicka, $obsah)->tFormat(get('format'), null, 0);
