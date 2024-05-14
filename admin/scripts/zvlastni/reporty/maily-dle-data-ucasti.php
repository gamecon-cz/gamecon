<?php
// Maily - nedávní účastníci

require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;

$prihlasen       = Role::VYZNAM_PRIHLASEN;
$pritomen        = Role::VYZNAM_PRITOMEN;
$soucasnyRocnik  = ROCNIK;
$predchoziRocnik = ROCNIK - 1;
$zacatekSoucasnehoRocniku = (new DateTime("$soucasnyRocnik-01-01"))->format('Y-m-d');

$report = Report::zSql(<<<SQL
SELECT
    uzivatele_hodnoty.email1_uzivatele AS email,
    IF (uzivatele_hodnoty.registrovan > '{$zacatekSoucasnehoRocniku}', 1, 0) AS novacek,
    EXISTS(SELECT * FROM role_seznam AS letos_prihlaseni
        JOIN uzivatele_role
            ON letos_prihlaseni.id_role = uzivatele_role.id_role
        WHERE letos_prihlaseni.vyznam_role = '$prihlasen'
            AND letos_prihlaseni.rocnik_role = $soucasnyRocnik
            AND uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
    ) AS letos_prihlasen,
    EXISTS(SELECT * FROM role_seznam AS loni_pritomni
        JOIN uzivatele_role
            ON loni_pritomni.id_role = uzivatele_role.id_role
        WHERE loni_pritomni.vyznam_role = '$pritomen'
            AND loni_pritomni.rocnik_role = $predchoziRocnik
            AND uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
    ) AS loni_pritomen,
    EXISTS(SELECT * FROM role_seznam AS loni_prihlaseni
        JOIN uzivatele_role
            ON loni_prihlaseni.id_role = uzivatele_role.id_role
        WHERE loni_prihlaseni.vyznam_role = '$prihlasen'
            AND loni_prihlaseni.rocnik_role = $predchoziRocnik
            AND uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
    ) AS loni_prihlasen,
    (SELECT MAX(pritomni.rocnik_role)
     FROM role_seznam AS pritomni
     JOIN uzivatele_role ON pritomni.id_role = uzivatele_role.id_role
     WHERE pritomni.vyznam_role = '$pritomen'
        AND uzivatele_role.id_uzivatele = uzivatele_hodnoty.id_uzivatele
    ) AS naposledy_pritomen
FROM uzivatele_hodnoty
WHERE uzivatele_hodnoty.nechce_maily IS NULL
    AND uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY
    IF (novacek = 1 AND letos_prihlasen = 0, 1, 0) DESC, -- noví a nerozhodní
    letos_prihlasen DESC,
    loni_pritomen DESC,
    loni_prihlasen DESC,
    naposledy_pritomen DESC,
    uzivatele_hodnoty.id_uzivatele ASC
SQL,
);

$report->tFormat(get('format'));
