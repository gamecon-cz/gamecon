<?php

use Gamecon\Shop\Shop;
use Gamecon\Role\Role;

require __DIR__ . '/sdilene-hlavicky.php';

$typTricko                 = Shop::TRICKO;
$typPredmet                = Shop::PREDMET;
$typJidlo                  = Shop::JIDLO;
$rok                       = ROCNIK;
$idckaRoliSOrganizatorySql = implode(',', Role::dejIdckaRoliSOrganizatory());

$poddotazKoupenehoPredmetu = static function (string $klicoveSlovo, int $idTypuPredmetu, int $rok) {
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR ', ')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), shop_predmety.nazev) AS pocet_a_nazev,
                 shop_nakupy.id_uzivatele
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
       IF (COUNT(platne_role_uzivatelu.id_role) > 0, 'org', '') AS role,
       {$poddotazKoupenehoPredmetu('', $typTricko, $rok)} AS tricka,
       {$poddotazKoupenehoPredmetu('taška', $typPredmet, $rok)} AS tasky,
       {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok)} AS kostky,
       {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok)} AS placky,
       {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok)} AS nicknacky,
       {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok)} AS bloky,
       {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok)} AS ponozky,
       IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok)} IS NULL, '', 'stravenky') AS stravenky
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu
    ON uzivatele_hodnoty.id_uzivatele = platne_role_uzivatelu.id_uzivatele
       AND platne_role_uzivatelu.id_role IN ($idckaRoliSOrganizatorySql)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL,
);

$report->tFormat(get('format'));
