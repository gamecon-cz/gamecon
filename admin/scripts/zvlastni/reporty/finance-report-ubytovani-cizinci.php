<?php
/**
 * Report ubytovaných cizinců = účastníci s objednaným ubytováním a státním občanstvím různým od "CZE".
 * Slouží kolejím pro evidenci cizinců. Struktura vychází z finance-report-ubytovani.php.
 */
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;
use App\Enum\ProductTagCode;
use Gamecon\Cas\DateTimeCz;

$o = dbQuery(<<<SQL
SELECT
    uzivatele.id_uzivatele,
    uzivatele.login_uzivatele,
    uzivatele.jmeno_uzivatele,
    uzivatele.prijmeni_uzivatele,
    uzivatele.statni_obcanstvi,
    '' AS datum_narozeni, -- placeholder, hodnotu dáme v PHP
    uzivatele.mesto_uzivatele,
    uzivatele.ulice_a_cp_uzivatele,
    uzivatele.typ_dokladu_totoznosti AS typ_dokladu,
    '' AS cislo_dokladu, -- placeholder
    IF(uzivatele.formular_cizince_od IS NOT NULL AND YEAR(uzivatele.formular_cizince_od) = $1, 'ano', 'ne') AS formular_cizince,
    GROUP_CONCAT(DISTINCT LEFT(product_variant.code, CHAR_LENGTH(product_variant.code) - 3)) AS typ,
    MIN(product_variant.accommodation_day) as prvni_noc,
    MAX(product_variant.accommodation_day) as posledni_noc,
    GROUP_CONCAT(DISTINCT IF(ubytovani.pokoj = '', NULL, ubytovani.pokoj)) as pokoj
FROM uzivatele_hodnoty uzivatele
JOIN platne_role_uzivatelu
    ON uzivatele.id_uzivatele=platne_role_uzivatelu.id_uzivatele AND platne_role_uzivatelu.id_role=$0 -- přihlášení na gc
JOIN shop_nakupy nakupy
    ON nakupy.id_uzivatele=uzivatele.id_uzivatele AND nakupy.rok=$1 -- nákupy tento rok
JOIN product_variant
    ON product_variant.id = nakupy.variant_id
JOIN product_product_tag
    ON product_product_tag.product_id = product_variant.product_id
JOIN product_tag
    ON product_tag.id = product_product_tag.tag_id AND product_tag.code = $2
LEFT JOIN ubytovani
    ON ubytovani.id_uzivatele=uzivatele.id_uzivatele
        AND ubytovani.rok=$1
        AND ubytovani.den = product_variant.accommodation_day
WHERE TRIM(uzivatele.statni_obcanstvi) <> ''
GROUP BY uzivatele.id_uzivatele
ORDER BY uzivatele.statni_obcanstvi, uzivatele.prijmeni_uzivatele
SQL,
    [
        Role::PRIHLASEN_NA_LETOSNI_GC,
        ROCNIK,
        ProductTagCode::UBYTOVANI->value,
    ],
);

$vystup = [];
while ($r = $o->fetch(PDO::FETCH_ASSOC)) {
    // České občanství ve všech variantách (ČR/CZ/České/…) vyfiltrujeme až tady, ať je logika
    // jednotná s Uzivatel::jeCizinec() a nedrifuje oproti SQL. Šetří to i instancování Uzivatele.
    if (Uzivatel::jeCeskeObcanstvi($r['statni_obcanstvi'])) {
        continue;
    }
    $u                   = Uzivatel::zId($r['id_uzivatele']);
    $r['datum_narozeni'] = $u->datumNarozeni()->format(DateTimeCz::FORMAT_DATUM_STANDARD);
    $r['cislo_dokladu']  = $u->cisloOp();
    $vystup[]            = $r;
}

Report::zPole($vystup)->tFormat(get('format'));
