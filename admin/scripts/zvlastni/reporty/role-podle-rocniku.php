<?php

require __DIR__ . '/sdilene-hlavicky.php';

Report::zSql(<<<SQL
SELECT
    COUNT(*) AS "Počet uživatelů",
    uzivatele_role_podle_rocniku.rocnik AS "Ročník",
    role_seznam.id_role,
    role_seznam.nazev_role AS "Role"
FROM uzivatele_role_podle_rocniku
JOIN role_seznam ON uzivatele_role_podle_rocniku.id_role = role_seznam.id_role
GROUP BY uzivatele_role_podle_rocniku.id_role, role_seznam.nazev_role, uzivatele_role_podle_rocniku.rocnik
ORDER BY uzivatele_role_podle_rocniku.rocnik DESC, role_seznam.nazev_role
SQL
)->tFormat(get('format'));
