<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik   = $systemoveNastaveni->rocnik();
$systemId = Uzivatel::SYSTEM;

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
LEFT JOIN uzivatele_hodnoty AS provedl
    ON provedl.id_uzivatele = platby.provedl
LEFT JOIN uzivatele_hodnoty AS prijemce
    ON prijemce.id_uzivatele = platby.id_uzivatele
WHERE platby.rok = $rocnik AND platby.provedl != $systemId
ORDER BY id_pohybu
SQL
);
$report->tFormat(get('format'));
