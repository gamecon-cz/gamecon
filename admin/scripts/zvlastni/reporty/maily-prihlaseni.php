<?php

use Gamecon\Role\Role;

require __DIR__ . '/sdilene-hlavicky.php';

// Záměrně jsou zahrnutí i uživatelé co nechtějí maily – pokud už se přihlásili, musíme mít možnost je informovat o daném GC.
$query = <<<SQL
SELECT uzivatele_hodnoty.email1_uzivatele
FROM uzivatele_hodnoty
JOIN platne_role_uzivatelu
    ON platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platne_role_uzivatelu.id_role = $0 -- přihlášen na letošní GC
WHERE uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
ORDER BY uzivatele_hodnoty.email1_uzivatele
SQL;

$report = Report::zSql($query, [0 => Role::PRIHLASEN_NA_LETOSNI_GC]);
$report->tFormat(get('format'));
