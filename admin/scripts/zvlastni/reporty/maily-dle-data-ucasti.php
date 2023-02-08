<?php

require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Zidle;

$prihlasen = Zidle::VYZNAM_PRIHLASEN;

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.email1_uzivatele
FROM uzivatele_hodnoty
LEFT JOIN r_uzivatele_zidle
    ON r_uzivatele_zidle.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN r_zidle_soupis
    ON r_uzivatele_zidle.id_zidle = r_zidle_soupis.id_zidle
        AND r_zidle_soupis.vyznam = '$prihlasen'
WHERE uzivatele_hodnoty.nechce_maily IS NULL
    AND uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY MAX(COALESCE(r_zidle_soupis.rocnik, 0)) DESC -- 0 je "Nikdy se na GC ještě nepřihlásil" a je proto v seznamu až dole
SQL
);

$report->tFormat(get('format'));
