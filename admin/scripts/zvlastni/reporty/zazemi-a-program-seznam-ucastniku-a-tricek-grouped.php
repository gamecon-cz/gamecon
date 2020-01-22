<?php
require_once __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele, GROUP_CONCAT(p.nazev SEPARATOR ',')
FROM uzivatele_hodnoty u
LEFT JOIN shop_nakupy n ON (n.id_uzivatele = u.id_uzivatele)
LEFT JOIN shop_predmety p ON (p.id_predmetu = n.id_predmetu)
WHERE (n.rok = $1) AND (p.typ = 3)
GROUP BY u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
SQL
  , [ROK]
);

$report->tFormat(get('format'));
