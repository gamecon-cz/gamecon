<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q("
INSERT INTO reporty (nazev, dotaz)
VALUES (
        'Potvrzení pro návštěvníky mladší patnácti let',
        \"SELECT *
FROM uzivatele_hodnoty
WHERE (YEAR('{gcBeziOd}') - YEAR(datum_narozeni) -
       IF(DATE_FORMAT('{gcBeziOd}', '%m%d') < DATE_FORMAT(datum_narozeni, '%m%d'), 1, 0)) < 15
ORDER BY COALESCE(potvrzeni_zakonneho_zastupce, '0001-01-01') ASC,
         registrovan DESC;\"
);
");