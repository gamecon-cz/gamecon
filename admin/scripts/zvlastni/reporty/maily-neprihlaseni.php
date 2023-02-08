<?php

use Gamecon\Role\Zidle;

require __DIR__ . '/sdilene-hlavicky.php';

$query = <<<SQL
SELECT u.email1_uzivatele
FROM uzivatele_hodnoty u
LEFT JOIN platne_zidle_uzivatelu prihlasen
    ON prihlasen.id_uzivatele = u.id_uzivatele AND prihlasen.id_zidle = $0 -- prihlasen na letosni GC
WHERE u.email1_uzivatele LIKE '%@%'
    AND u.nechce_maily IS NULL
    AND prihlasen.id_zidle IS NULL -- nepřihlášen
ORDER BY email1_uzivatele
SQL;

$report = Report::zSql($query, [0 => Zidle::PRIHLASEN_NA_LETOSNI_GC]);
$report->tFormat(get('format'));
