<?php
require __DIR__ . '/sdilene-hlavicky.php';

use App\Enum\ProductTagCode;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Role;

$t = new XTemplate(__DIR__ . '/report-infopult-ucastnici-balicky.xtpl');

$typTricko                 = '$0';
$typPredmet                = '$1';
$typJidlo                  = '$2';
$rok                       = ROCNIK;
$idckaRoliSOrganizatorySql = implode(',', Role::dejIdckaRoliSOrganizatory());

// $parametrTypu is the placeholder ($0, $1, …) carrying the category's tag code
$poddotazKoupenehoPredmetu = static function (string $klicoveSlovo, string $parametrTypu, int $rok) {
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR '</li><li>')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), CONCAT_WS(' ', produkt.nazev, product_variant.name)) AS pocet_a_nazev, shop_nakupy.id_uzivatele
        FROM shop_nakupy
            JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
            JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
            JOIN product_product_tag ON product_product_tag.product_id = produkt.id_predmetu
            JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = {$parametrTypu}
            WHERE IF ('$klicoveSlovo' = '', TRUE, CONCAT_WS(' ', produkt.nazev, product_variant.name) LIKE '%{$klicoveSlovo}%')
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, CONCAT_WS(' ', produkt.nazev, product_variant.name)) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$poddotazOstatnichKoupeneychPredmetu = static function (array $mimoKlicovaSlova, string $parametrTypu, int $rok) {
    $mimoKlicovaSlovaSql = implode(' AND ', array_map(static function (string $klicoveSlovo) {
        return "CONCAT_WS(' ', produkt.nazev, product_variant.name) NOT LIKE '%{$klicoveSlovo}%'";
    }, $mimoKlicovaSlova));
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR '</li><li>')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), CONCAT_WS(' ', produkt.nazev, product_variant.name)) AS pocet_a_nazev, shop_nakupy.id_uzivatele
        FROM shop_nakupy
            JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
            JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
            JOIN product_product_tag ON product_product_tag.product_id = produkt.id_predmetu
            JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = {$parametrTypu}
            WHERE ($mimoKlicovaSlovaSql)
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, CONCAT_WS(' ', produkt.nazev, product_variant.name)) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$kolikTypuNakoupil = static function (array $parametryTypu, int $rok) {
    $parametryTypuSql = implode(', ', $parametryTypu);
    return <<<SQL
    (
        SELECT count(distinct(shop_nakupy.variant_id))
        FROM shop_nakupy
            JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
            JOIN product_product_tag ON product_product_tag.product_id = product_variant.product_id
            JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code IN ($parametryTypuSql)
        WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele
            AND shop_nakupy.rok = $rok
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
       {$poddotazKoupenehoPredmetu('', $typTricko, $rok)} AS tricka,
       {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok)} AS kostky,
       {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok)} AS placky,
       {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok)} AS nicknacky,
       {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok)} AS bloky,
       {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok)} AS ponozky,
       {$poddotazKoupenehoPredmetu('taška', $typPredmet, $rok)} AS tasky,
       {$poddotazOstatnichKoupeneychPredmetu(['kostka', 'placka', 'nicknack', 'blok', 'ponožky', 'taška'], $typPredmet, $rok)} AS ostatni,
       IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok)} IS NULL, '', 'stravenky') AS stravenky
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS role_organizatoru
    ON uzivatele_hodnoty.id_uzivatele = role_organizatoru.id_uzivatele AND role_organizatoru.id_role IN ({$idckaRoliSOrganizatorySql})
WHERE uzivatele_hodnoty.id_uzivatele IN (
    SELECT DISTINCT(sn.id_uzivatele)
    FROM shop_nakupy AS sn
    JOIN product_variant ON product_variant.id = sn.variant_id
    JOIN product_product_tag ON product_product_tag.product_id = product_variant.product_id
    JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code IN ({$typTricko}, {$typPredmet})
    WHERE sn.rok = $rok
)
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY uzivatele_hodnoty.id_uzivatele
SQL,
    [0 => ProductTagCode::TRICKO->value, 1 => ProductTagCode::PREDMET->value, 2 => ProductTagCode::JIDLO->value],
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
