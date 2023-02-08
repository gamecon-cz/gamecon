<?php

use Gamecon\Shop\Shop;
use Gamecon\Role\Zidle;

require __DIR__ . '/sdilene-hlavicky.php';

$typTricko = Shop::TRICKO;
$typPredmet = Shop::PREDMET;
$typJidlo = Shop::JIDLO;
$rok = ROK;
$idckaZidliSOrganizatorySql = implode(',', Zidle::dejIdckaZidliSOrganizatory());

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
                AND shop_nakupy.rok = {$rok}
                AND IF ('{$klicoveSlovo}' = '', TRUE, shop_predmety.nazev LIKE '%{$klicoveSlovo}%')
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, shop_predmety.nazev) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele,
       uzivatele_hodnoty.login_uzivatele,
       uzivatele_hodnoty.jmeno_uzivatele,
       uzivatele_hodnoty.prijmeni_uzivatele,
       IF (COUNT(platne_zidle_uzivatelu.id_zidle) > 0, 'org', '') AS role,
       {$poddotazKoupenehoPredmetu('', $typTricko, $rok, false)} AS tricka,
       {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok, true)} AS kostky,
       {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok, false)} AS placky,
       {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok, false)} AS nicknacky,
       {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok, false)} AS bloky,
       {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok, false)} AS ponozky,
       IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok, false)} IS NULL, '', 'stravenky') AS stravenky
FROM uzivatele_hodnoty
LEFT JOIN platne_zidle_uzivatelu
    ON uzivatele_hodnoty.id_uzivatele = platne_zidle_uzivatelu.id_uzivatele
       AND platne_zidle_uzivatelu.id_zidle IN ($idckaZidliSOrganizatorySql)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
);

$report->tFormat(get('format'));
