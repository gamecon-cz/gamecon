<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Shop\Shop;

$typTricko = Shop::TRICKO;
$typPredmet = Shop::PREDMET;
$typJidlo = Shop::JIDLO;
$rok = ROK;
$idZidliSOrganizatorySql = implode(',', \Gamecon\Zidle::dejIdckaZidliSOrganizatory());

$poddotazKoupenehoPredmetu = static function (string $klicoveSlovo, int $idTypuPredmetu, int $rok, bool $prilepitRokKNazvu) {
    $rokKNazvu = $prilepitRokKNazvu
        ? " $rok"
        : '';
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR ', ')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), CONCAT(shop_predmety.nazev, '$rokKNazvu')) AS pocet_a_nazev, shop_nakupy.id_uzivatele
        FROM shop_nakupy
            JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
            WHERE shop_predmety.id_predmetu = shop_nakupy.id_predmetu
                AND shop_predmety.typ = {$idTypuPredmetu}
                AND IF ('$klicoveSlovo' = '', TRUE, shop_predmety.nazev LIKE '%{$klicoveSlovo}%')
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, shop_predmety.nazev) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$poddotazOstatnichKoupeneychPredmetu = static function (array $mimoKlicovaSlova, int $idTypuPredmetu, int $rok) {
    $mimoKlicovaSlovaSql = implode(' AND ', array_map(static function (string $klicoveSlovo) {
        return "shop_predmety.nazev NOT LIKE '%{$klicoveSlovo}%'";
    }, $mimoKlicovaSlova));
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR ', ')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), shop_predmety.nazev) AS pocet_a_nazev, shop_nakupy.id_uzivatele
        FROM shop_nakupy
            JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
            WHERE shop_predmety.id_predmetu = shop_nakupy.id_predmetu
                AND shop_predmety.typ = {$idTypuPredmetu}
                AND ($mimoKlicovaSlovaSql)
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, shop_predmety.nazev) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$kopilNecoSql = static function (array $typyPredmetu, int $rok) {
    $typyPredmetuSql = implode(',', array_map('intval', $typyPredmetu));
    return <<<SQL
EXISTS(
    SELECT 1
    FROM shop_predmety
        JOIN shop_nakupy ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
    WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele
        AND shop_nakupy.rok = $rok
        AND shop_predmety.typ IN ($typyPredmetuSql)
)
SQL;
};

$prihlasenNaLetosniGc = (int)\Gamecon\Zidle::PRIHLASEN_NA_LETOSNI_GC;

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele,
       uzivatele_hodnoty.login_uzivatele,
       uzivatele_hodnoty.jmeno_uzivatele,
       uzivatele_hodnoty.prijmeni_uzivatele,
       IF (COUNT(zidle_organizatoru.id_zidle) > 0, 'org', '') AS role,
       {$poddotazKoupenehoPredmetu('', $typTricko, $rok, false)} AS tricka,
       {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok, true)} AS kostky,
       {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok, false)} AS placky,
       {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok, false)} AS nicknacky,
       {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok, false)} AS bloky,
       {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok, false)} AS ponozky,
       {$poddotazKoupenehoPredmetu('taška', $typPredmet, $rok, false)} AS tasky,
       {$poddotazOstatnichKoupeneychPredmetu(['kostka', 'placka', 'nicknack', 'blok', 'ponožky', 'taška'], $typPredmet, $rok)} AS ostatni,
       IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok, false)} IS NULL, '', 'stravenky') AS stravenky,
       IF (
            {$kopilNecoSql([$typTricko, $typPredmet], $rok)},
            IF (uzivatele_hodnoty.infopult_poznamka = 'velký balíček $rok', 'velký balíček', 'balíček'),
           ''
       ) AS balicek
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle
    ON uzivatele_hodnoty.id_uzivatele = r_uzivatele_zidle.id_uzivatele
LEFT JOIN r_uzivatele_zidle AS zidle_organizatoru
    ON uzivatele_hodnoty.id_uzivatele = zidle_organizatoru.id_uzivatele AND zidle_organizatoru.id_zidle IN ($idZidliSOrganizatorySql)
WHERE r_uzivatele_zidle.id_zidle = {$prihlasenNaLetosniGc}
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
);

$report->tFormat(get('format'));
