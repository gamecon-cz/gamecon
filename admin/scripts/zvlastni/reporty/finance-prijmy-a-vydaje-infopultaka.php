<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
    CONCAT('platba-', platby.id) AS id_pohybu,
    provedl.id_uzivatele AS objenatel_id_uzivatele,
    provedl.jmeno_uzivatele AS objenatel_jmeno_uzivatele,
    provedl.prijmeni_uzivatele AS objenatel_prijmeni_uzivatele,
    prijemce.id_uzivatele AS zakaznik_id_uzivatele,
    prijemce.jmeno_uzivatele AS zakaznik_jmeno_uzivatele,
    prijemce.prijmeni_uzivatele AS zakaznik_prijmeni_uzivatele,
    platby.castka,
    DATE(platby.provedeno) AS datum,
    TIME(platby.provedeno) AS cas,
    platby.poznamka
FROM platby
LEFT JOIN uzivatele_hodnoty AS provedl ON provedl.id_uzivatele = platby.provedl
LEFT JOIN uzivatele_hodnoty AS prijemce ON prijemce.id_uzivatele = platby.id_uzivatele
WHERE rok = $0 AND platby.provedl != $1
UNION ALL
SELECT
    CONCAT('prodej-', nakupy.id_nakupu) AS id_pohybu,
    provedl.id_uzivatele AS objenatel_id_uzivatele,
    provedl.jmeno_uzivatele AS objenatel_jmeno_uzivatele,
    provedl.prijmeni_uzivatele AS objenatel_prijmeni_uzivatele,
    '' AS zakaznik_id_uzivatele,
    'Anonym' AS zakaznik_jmeno_uzivatele,
    'Anonym' AS zakaznik_prijmeni_uzivatele,
    nakupy.cena_nakupni AS castka,
    DATE(nakupy.datum) AS datum,
    TIME(nakupy.datum) AS cas,
    predmety.nazev AS poznamka
FROM
    shop_nakupy AS nakupy
LEFT JOIN shop_predmety AS predmety on nakupy.id_predmetu = predmety.id_predmetu
LEFT JOIN uzivatele_hodnoty AS provedl ON provedl.id_uzivatele = nakupy.id_objednatele
LEFT JOIN uzivatele_hodnoty AS prijemce ON prijemce.id_uzivatele = nakupy.id_uzivatele
WHERE nakupy.rok = $0 AND provedl.id_uzivatele != $1 AND prijemce.id_uzivatele = $1 -- pouze anonymní prodej
ORDER BY id_pohybu
SQL
    , [0 => ROCNIK, 1 => Uzivatel::SYSTEM],
);
$report->tFormat(get('format'));
