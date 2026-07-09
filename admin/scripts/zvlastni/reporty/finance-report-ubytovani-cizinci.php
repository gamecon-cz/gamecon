<?php
/**
 * Report ubytovaných cizinců = účastníci s objednaným ubytováním a státním občanstvím různým od "CZE".
 * Slouží kolejím pro evidenci cizinců. Struktura vychází z finance-report-ubytovani.php.
 */
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
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
    GROUP_CONCAT(DISTINCT LEFT(predmety.kod_predmetu, CHAR_LENGTH(predmety.kod_predmetu) - 3)) AS typ,
    MIN(predmety.ubytovani_den) as prvni_noc,
    MAX(predmety.ubytovani_den) as posledni_noc,
    GROUP_CONCAT(DISTINCT IF(ubytovani.pokoj = '', NULL, ubytovani.pokoj)) as pokoj
FROM uzivatele_hodnoty uzivatele
JOIN platne_role_uzivatelu
    ON uzivatele.id_uzivatele=platne_role_uzivatelu.id_uzivatele AND platne_role_uzivatelu.id_role=$0 -- přihlášení na gc
JOIN shop_nakupy nakupy
    ON nakupy.id_uzivatele=uzivatele.id_uzivatele AND nakupy.rok=$1 -- nákupy tento rok
JOIN shop_predmety predmety
    ON predmety.id_predmetu=nakupy.id_predmetu AND predmety.typ=$2 -- info o předmětech k nákupům
LEFT JOIN ubytovani
    ON ubytovani.id_uzivatele=uzivatele.id_uzivatele
        AND ubytovani.rok=$1
        AND ubytovani.den = predmety.ubytovani_den
WHERE TRIM(uzivatele.statni_obcanstvi) <> ''
GROUP BY uzivatele.id_uzivatele
ORDER BY uzivatele.statni_obcanstvi, uzivatele.prijmeni_uzivatele
SQL,
    [
        Role::PRIHLASEN_NA_LETOSNI_GC,
        ROCNIK,
        TypPredmetu::UBYTOVANI,
    ],
);

$vystup = [];
while ($r = mysqli_fetch_assoc($o)) {
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
