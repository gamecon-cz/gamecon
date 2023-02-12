<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Cas\DateTimeCz;
use Gamecon\Report\KonfiguraceReportu;
use Gamecon\Role\Role;
use Gamecon\Shop\Shop;

require __DIR__ . '/sdilene-hlavicky.php';

require_once __DIR__ . '/_bfgr_pomocne.php';

function excelDatum($datum) {
    if (!$datum) {
        return null;
    }
    return date('j.n.Y G:i', strtotime($datum));
}

function excelCislo($cislo) {
    return str_replace('.', ',', $cislo);
}

function typUbytovani($typ) { // ubytování typ - z názvu předmětu odhadne typ
    return preg_replace('@ ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle) ?@iu', '', $typ);
}

// poradi je dulezite, udava prioritu
$idRoliiProPozici = [
    Role::ORGANIZATOR,
    Role::ORGANIZATOR_S_BONUSY_1,
    Role::ORGANIZATOR_S_BONUSY_2,
    Role::LETOSNI_VYPRAVEC,
    Role::LETOSNI_PARTNER,
    Role::LETOSNI_DOBROVOLNIK_SENIOR,
];
$jmenaRoliProPozici = [];
foreach ($idRoliiProPozici as $idRole) {
    $jmenaRoliProPozici[$idRole] = Role::zId($idRole)->jmenoRole();
}
$dejNazevRole = static function (array $idckaRoli) use ($jmenaRoliProPozici): string {
    foreach ($jmenaRoliProPozici as $idRole => $jmenoRole) {
        if (in_array($idRole, $idckaRoli, false)) {
            return $jmenoRole;
        }
    }
    return 'Účastník';
};

$ucastPodleRoku = [];
$maxRok         = po(REG_GC_DO) ? ROCNIK : ROCNIK - 1;
for ($i = 2009; $i <= $maxRok; $i++) {
    $ucastPodleRoku[$i] = 'účast ' . $i;
}

$letosniPlacky = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%placka%' COLLATE utf8_czech_ci
AND stav > 0
SQL, [ROCNIK]
);

$poradiKostek    = [
    'kostka zdarma',
    'Kostka Cthulhu 2021',
    'Fate kostka 2021',
    'Kostka 2018',
    'Kostka 2012',
];
$poradiKostekSql = implode(',', $poradiKostek);
$letosniKostky   = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok)
FROM shop_predmety
WHERE nazev LIKE '%kostka%' COLLATE utf8_czech_ci
AND stav > 0
ORDER BY FIND_IN_SET(CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok), '{$poradiKostekSql}')
SQL, [ROCNIK]
);

$letosniJidla = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, TRIM(shop_predmety.nazev)
FROM shop_predmety
WHERE shop_predmety.typ = $1
AND model_rok = $2
ORDER BY FIELD(SUBSTRING(TRIM(shop_predmety.nazev), 1, POSITION(' ' IN TRIM(shop_predmety.nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře'),
         FIELD(SUBSTRING(TRIM(shop_predmety.nazev), POSITION(' ' IN TRIM(shop_predmety.nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle')
SQL, [Shop::JIDLO, ROCNIK]
);

$letosniOstatniPredmety = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu,
       IF(model_rok != $1, CONCAT_WS(' ', TRIM(shop_predmety.nazev), model_rok), shop_predmety.nazev) AS nazev
FROM shop_predmety
WHERE shop_predmety.typ = $2
AND stav > 0
AND (TRIM(nazev) IN ('GameCon blok', 'Nicknack') OR nazev LIKE '%ponožky%' COLLATE utf8_czech_ci)
ORDER BY TRIM(shop_predmety.nazev)
SQL, [ROCNIK, Shop::PREDMET]
);

$letosniCovidTesty = dbFetchPairs(<<<SQL
SELECT shop_predmety.id_predmetu, TRIM(shop_predmety.nazev)
FROM shop_predmety
WHERE shop_predmety.typ = $1
AND stav > 0
AND TRIM(nazev) LIKE '%COVID%' COLLATE utf8_czech_ci
ORDER BY TRIM(shop_predmety.nazev)
SQL, [Shop::PREDMET]
);

$rok              = ROCNIK;
$predmetUbytovani = \Gamecon\Shop\TypPredmetu::UBYTOVANI;
$typUcast         = Role::TYP_UCAST;
$o                = dbQuery(<<<SQL
SELECT
    uzivatele_hodnoty.*,
    prihlasen.posazen AS prihlasen_na_gc_kdy,
    pritomen.posazen as prosel_infopultem_kdy,
    odjel.posazen as odjel_kdy,
    ( SELECT MIN(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS den_prvni,
    ( SELECT MAX(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS den_posledni,
    ( SELECT MAX(shop_predmety.nazev) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok=$rok AND shop_nakupy.id_uzivatele=prihlasen.id_uzivatele AND shop_predmety.typ=$predmetUbytovani ) AS ubytovani_typ,
    ( SELECT GROUP_CONCAT(r_prava_soupis.jmeno_prava SEPARATOR ', ')
      FROM platne_role_uzivatelu
      JOIN prava_role
          ON platne_role_uzivatelu.id_role = prava_role.id_role
      JOIN r_prava_soupis
          ON prava_role.id_prava = r_prava_soupis.id_prava
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS pravaZDotazu,
    ( SELECT GROUP_CONCAT(role_seznam.nazev_role ORDER BY role_seznam.id_role DESC SEPARATOR ', ')
      FROM role_seznam
      JOIN platne_role_uzivatelu
          ON role_seznam.id_role = platne_role_uzivatelu.id_role
      WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role = '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS ucastZDotazu,
    ( SELECT GROUP_CONCAT(platne_role_uzivatelu.id_role SEPARATOR ',')
      FROM platne_role_uzivatelu
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS idckaRoliZDotazu,
    ( SELECT GROUP_CONCAT(role_seznam.nazev_role SEPARATOR ', ')
      FROM platne_role_uzivatelu
      JOIN role_seznam
          ON platne_role_uzivatelu.id_role = role_seznam.id_role
      WHERE platne_role_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele
          AND role_seznam.typ_role != '$typUcast'
      GROUP BY platne_role_uzivatelu.id_uzivatele
    ) AS roleZDotazu
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS prihlasen ON (prihlasen.id_role = $0 AND prihlasen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN platne_role_uzivatelu AS pritomen ON (pritomen.id_role = $1 AND pritomen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
LEFT JOIN platne_role_uzivatelu AS odjel ON(odjel.id_role = $2 AND odjel.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
WHERE prihlasen.id_uzivatele IS NOT NULL -- left join, takže může být NULL ve smyslu "nemáme záznam" = "není přihlášen"
    OR pritomen.id_uzivatele IS NOT NULL -- tohle by bylo hodně divné, musela by být díra v systému, aby nebyl přihlášen ale byl přítomen, ale radši...
    OR EXISTS(SELECT * FROM shop_nakupy WHERE uzivatele_hodnoty.id_uzivatele = shop_nakupy.id_uzivatele AND shop_nakupy.rok = $rok)
    OR EXISTS(SELECT * FROM platby WHERE platby.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platby.rok = $rok)
SQL,
    [0 => Role::PRIHLASEN_NA_LETOSNI_GC, 1 => Role::PRITOMEN_NA_LETOSNIM_GC, 2 => Role::ODJEL_Z_LETOSNIHO_GC]
);
if (mysqli_num_rows($o) === 0) {
    exit('V tabulce nejsou žádná data.');
}

$letosniPlackyKlice          = array_fill_keys($letosniPlacky, null);
$letosniKostkyKlice          = array_fill_keys($letosniKostky, null);
$letosniJidlaKlice           = array_fill_keys($letosniJidla, null);
$letosniOstatniPredmetyKlice = array_fill_keys($letosniOstatniPredmety, null);
$letosniCovidTestyKlice      = array_fill_keys($letosniCovidTesty, null);

while ($r = mysqli_fetch_assoc($o)) {
    $navstevnik = new Uzivatel($r);
    $navstevnik->nactiPrava(); // sql subdotaz, zlo
    $finance        = $navstevnik->finance();
    $ucastiHistorie = [];
    foreach ($ucastPodleRoku as $rok => $nazevUcasti) {
        $ucastiHistorie[$nazevUcasti] = $navstevnik->maPravo((int)('-' . substr($rok, 2) . '02')) ? 'ano' : 'ne';
    }
    $stat = '';
    try {
        $stat = $navstevnik->stat();
    } catch (Exception $e) {
    }

    $letosniOstatniPredmetyPocty = array_intersect_key($r, $letosniOstatniPredmetyKlice);

    $obsah[] = array_merge(
        [
            'Účastník'            => [
                'ID'                => $r['id_uzivatele'],
                'Příjmení'          => $r['prijmeni_uzivatele'],
                'Jméno'             => $r['jmeno_uzivatele'],
                'Přezdívka'         => $r['login_uzivatele'],
                'Mail'              => $r['email1_uzivatele'],
                'Pozice'            => $dejNazevRole(explode(',', (string)$r['idckaRoliZDotazu'])),
                'Role'              => $r['roleZDotazu'],
                'Práva'             => nahradNazvyKonstantZaHodnoty((string)$r['pravaZDotazu']),
                'Účast'             => $r['ucastZDotazu'],
                'Datum registrace'  => excelDatum($r['prihlasen_na_gc_kdy']),
                'Prošel infopultem' => excelDatum($r['prosel_infopultem_kdy']),
                'Odjel kdy'         => excelDatum($r['odjel_kdy']),
            ],
            'Datum narození'      => [
                'Den'   => date('j', strtotime($r['datum_narozeni'])),
                'Měsíc' => date('n', strtotime($r['datum_narozeni'])),
                'Rok'   => date('Y', strtotime($r['datum_narozeni'])),
            ],
            'Bydliště'            => [
                'Stát'  => $stat,
                'Město' => $r['mesto_uzivatele'],
                'Ulice' => $r['ulice_a_cp_uzivatele'],
                'PSČ'   => $r['psc_uzivatele'],
                'Škola' => $r['skola'],
            ],
            'Ubytovací informace' => array_merge(
                [
                    'Chci bydlet s'          => $r['ubytovan_s'],
                    'První noc'              => $r['den_prvni'] === null
                        ? '-'
                        : (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add(new DateInterval("P$r[den_prvni]D"))->format('j.n.Y'),
                    'Poslední noc (počátek)' => $r['den_posledni'] === null
                        ? '-'
                        : (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add(new DateInterval("P$r[den_posledni]D"))->format('j.n.Y'),
                    'Typ'                    => typUbytovani((string)$r['ubytovani_typ']),
                    'Dorazil na GC'          => $navstevnik->gcPritomen() ? 'ano' : 'ne',
                ],
                $ucastiHistorie
            ),
        ],
        [
            'Celkové náklady' => [
                'Celkem dní' => $pobyt = ($r['den_prvni'] !== null
                    ? $r['den_posledni'] - $r['den_prvni'] + 1
                    : 0
                ),
                'Cena / den' => $pobyt ? $finance->cenaUbytovani() / $pobyt : 0,
                'Ubytování'  => $finance->cenaUbytovani(),
                'Předměty'   => $finance->cenaPredmetu(),
                'Strava'     => $finance->cenaStravy(),
            ],
            'Ostatní platby'  => [
                'Aktivity'                           => $finance->cenaAktivit(),
                'Dobrovolné vstupné'                 => $finance->vstupne(),
                'Dobrovolné vstupné (pozdě)'         => $finance->vstupnePozde(),
                'Suma slev'                          => excelCislo($finance->slevaObecna()),
                'Bonus za vedení aktivit'            => $finance->bonusZaVedeniAktivit(),
                'Využitý bonus za vedení aktivit'    => $finance->vyuzityBonusZaAktivity(),
                'Proplacený bonus za vedení aktivit' => $finance->proplacenyBonusZaAktivity(),
                'Brigádnické odměny'                 => $finance->brigadnickaOdmena(),
                'Stav'                               => excelCislo($finance->stav()),
                'Zůstatek z minula'                  => excelCislo($r['zustatek']),
                'Připsané platby'                    => excelCislo($finance->sumaPlateb()),
                'První blok'                         => excelDatum($navstevnik->prvniBlok()),
                'Poslední blok'                      => excelDatum($navstevnik->posledniBlok()),
                'Dobrovolník pozice'                 => $r['pomoc_typ'],
                'Dobrovolník info'                   => $r['pomoc_vice'],
                'Storno aktivit'                     => $finance->sumaStorna(),
                'Dárky a zlevněné nákupy'            => implode(", ", array_merge($finance->slevyVse(), $finance->slevyAktivity())),
                'Objednávky'                         => strip_tags($finance->prehledPopis()),
                'Poznámka'                           => strip_tags((string)$r['poznamka']),
            ],
            'Eshop'           => array_merge(
                [
                    'Průměrná sleva na aktivity %' => $finance->slevaZaAktivityVProcentech(),
                ],
                dejNazvyAPoctyPlacek($navstevnik),
                dejNazvyAPoctyKostek($navstevnik, $letosniKostky),
                dejNazvyAPoctyJidel($navstevnik, $letosniJidla),
                dejNazvyAPoctySvrsku($navstevnik),
                dejNazvyAPoctyOstatnichPredmetu($navstevnik, $letosniOstatniPredmety),
//                $letosniOstatniPredmetyPocty,
                dejNazvyAPoctyCovidTestu($navstevnik, $letosniCovidTesty) // "dát pls až nakonec", tak pravil Gandalf 30. 7. 2021
            ),
        ],
    );
}

$indexySloupcuSBydlistem = Report::dejIndexyKlicuPodsloupcuDruhehoRadkuDleKliceVPrvnimRadku('Bydliště', $obsah);
$sirkaSloupcuSBydlistem  = array_fill_keys($indexySloupcuSBydlistem, 30);

$indexySloupcuSDatemNarozeni = Report::dejIndexyKlicuPodsloupcuDruhehoRadkuDleKliceVPrvnimRadku('Datum narození', $obsah);
$sirkaSloupcuSDatemNarozeni  = array_fill_keys($indexySloupcuSDatemNarozeni, 10);

$indexSloupceSPravy = Report::dejIndexKlicePodsloupceDruhehoRadku('Práva', $obsah);
$sirkaSloupcuSPravy = [$indexSloupceSPravy => 50];

$konfiguraceReportu = (new KonfiguraceReportu())
    ->setRowToFreeze(KonfiguraceReportu::NO_ROW_TO_FREEZE)
    ->setMaxGenericColumnWidth(50)
    ->setColumnsWidths($sirkaSloupcuSBydlistem + $sirkaSloupcuSDatemNarozeni + $sirkaSloupcuSPravy);

Report::zPoleSDvojitouHlavickou($obsah, Report::HLAVICKU_ZACINAT_VElKYM_PISMENEM)
    ->tFormat(get('format'), null, $konfiguraceReportu);
