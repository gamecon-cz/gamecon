<?php

use Gamecon\Role\Role;

require __DIR__ . '/sdilene-hlavicky.php';

$query = <<<SQL
SELECT u.email1_uzivatele
FROM uzivatele_hodnoty u
LEFT JOIN platne_role_uzivatelu prihlasen
    ON prihlasen.id_uzivatele = u.id_uzivatele AND prihlasen.id_role = $0 -- prihlasen na letosni GC
WHERE u.email1_uzivatele LIKE '%@%'
    AND u.nechce_maily IS NULL
    AND prihlasen.id_role IS NULL -- nepřihlášen
ORDER BY email1_uzivatele
SQL;

$report = Report::zSql($query, [0 => Role::PRIHLASEN_NA_LETOSNI_GC]);
$report->tFormat(get('format'));
