<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
SELECT uh.id_uzivatele id, uh.login_uzivatele login, uh.jmeno_uzivatele jmeno, uh.prijmeni_uzivatele prijmeni, CONCAT('<a href="../infopult/potvrzeni-rodicu?id=', uh.id_uzivatele, '">potvrzení</a>') odkaz, uh.potvrzeni_zakonneho_zastupce_soubor nahrano_kdy
FROM uzivatele_hodnoty uh
WHERE uh.potvrzeni_zakonneho_zastupce_soubor IS NOT NULL
  AND (uh.potvrzeni_zakonneho_zastupce IS NULL OR DATE(uh.potvrzeni_zakonneho_zastupce_soubor) > uh.potvrzeni_zakonneho_zastupce)
SQL,
);

$report->tFormat(get('format'));
