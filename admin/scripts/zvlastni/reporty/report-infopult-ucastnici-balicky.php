<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

$t = new XTemplate(__DIR__ . '/report-infopult-ucastnici-balicky.xtpl');

$typTricko = Shop::TRICKO;
$typPredmet = Shop::PREDMET;
$typJidlo = Shop::JIDLO;
$rok = ROCNIK;
$idckaRoliSOrganizatorySql = implode(',', \Gamecon\Role\Role::dejIdckaRoliSOrganizatory());

$poddotazKoupenehoPredmetu = static function (string $klicoveSlovo, int $idTypuPredmetu, int $rok, bool $prilepitRokKNazvu) {
    $rokKNazvu = $prilepitRokKNazvu
        ? " $rok"
        : '';
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR '</li><li>')
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
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR '</li><li>')
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

$kolikTypuNakoupil = static function (array $typyPredmetu, int $rok) {
    $typyPredmetuSql = implode(',', array_map('intval', $typyPredmetu));
    return <<<SQL
    (
        SELECT count(distinct(shop_nakupy.id_predmetu))
        FROM shop_predmety
            JOIN shop_nakupy ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
        WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele
            AND shop_nakupy.rok = $rok
            AND shop_predmety.typ IN ($typyPredmetuSql)
    )
    SQL;
};

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele,
       {$kolikTypuNakoupil([$typTricko, $typPredmet], $rok)} AS count_typu_predmetu,
       uzivatele_hodnoty.login_uzivatele AS login,
       uzivatele_hodnoty.jmeno_uzivatele AS jmeno,
       uzivatele_hodnoty.prijmeni_uzivatele AS prijmeni,
       IF (COUNT(role_organizatoru.id_role) > 0, 'org', '') AS role,
       {$poddotazKoupenehoPredmetu('', $typTricko, $rok, false)} AS tricka,
       {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok, true)} AS kostky,
       {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok, false)} AS placky,
       {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok, false)} AS nicknacky,
       {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok, false)} AS bloky,
       {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok, false)} AS ponozky,
       {$poddotazKoupenehoPredmetu('taška', $typPredmet, $rok, false)} AS tasky,
       {$poddotazOstatnichKoupeneychPredmetu(['kostka', 'placka', 'nicknack', 'blok', 'ponožky', 'taška'], $typPredmet, $rok)} AS ostatni,
       IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok, false)} IS NULL, '', 'stravenky') AS stravenky
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS role_organizatoru
    ON uzivatele_hodnoty.id_uzivatele = role_organizatoru.id_uzivatele AND role_organizatoru.id_role IN ({$idckaRoliSOrganizatorySql})
WHERE uzivatele_hodnoty.id_uzivatele IN (
    SELECT DISTINCT(sn.id_uzivatele)
    FROM shop_nakupy AS sn
    JOIN shop_predmety AS sp ON sp.id_predmetu = sn.id_predmetu AND sp.typ IN ({$typTricko}, {$typPredmet})
    WHERE sn.rok = $rok
)
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY uzivatele_hodnoty.id_uzivatele
SQL
);

$fn = static function ($radek) use ($t) {
    $t->assign('id_uzivatele', array_shift($radek));
    $t->assign('pocet_typu', array_shift($radek));
    $t->assign('login_uzivatele', array_shift($radek));
    $t->assign('jmeno_uzivatele', array_shift($radek));
    $t->assign('prijmeni_uzivatele', array_shift($radek));
    $t->assign('vsechno', implode('</li><li>', $radek));
    $t->parse('balicky.balicek');
};

$report->tXTemplate($fn);

$t->parse('balicky');
$t->out('balicky');
