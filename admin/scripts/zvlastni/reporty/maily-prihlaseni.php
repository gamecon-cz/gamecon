<?php

use Gamecon\Role\Zidle;

require __DIR__ . '/sdilene-hlavicky.php';

// Záměrně jsou zahrnutí i uživatelé co nechtějí maily – pokud už se přihlásili, musíme mít možnost je informovat o daném GC.
$query = <<<SQL
SELECT uzivatele_hodnoty.email1_uzivatele
FROM uzivatele_hodnoty
JOIN letos_platne_zidle_uzivatelu AS zidle_uzivatelu
    ON zidle_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND zidle_uzivatelu.id_zidle = $0 -- přihlášen na letošní GC
WHERE uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
ORDER BY uzivatele_hodnoty.email1_uzivatele
SQL;

$report = Report::zSql($query, [0 => Zidle::PRIHLASEN_NA_LETOSNI_GC]);
$report->tFormat(get('format'));
