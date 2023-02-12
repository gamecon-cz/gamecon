<?php

require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;

$prihlasen = Role::VYZNAM_PRIHLASEN;

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.email1_uzivatele
FROM uzivatele_hodnoty
LEFT JOIN uzivatele_role
    ON uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN role_seznam
    ON uzivatele_role.id_role = role_seznam.id_role
        AND role_seznam.vyznam_role = '$prihlasen'
WHERE uzivatele_hodnoty.nechce_maily IS NULL
    AND uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY MAX(COALESCE(role_seznam.rocnik_role, 0)) DESC -- 0 je "Nikdy se na GC ještě nepřihlásil" a je proto v seznamu až dole
SQL
);

$report->tFormat(get('format'));
