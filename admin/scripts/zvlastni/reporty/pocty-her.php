<?php

use \Gamecon\Aktivita\TypAktivity;

/**
 * Počty her a jednotlivých druhý her pro jednotlivé účastníky
 */

/** @var string $NAZEV_SKRIPTU */
require __DIR__ . '/sdilene-hlavicky.php';

$rok = ROK;

$result = dbQuery(<<<SQL
SELECT
    p.id_uzivatele AS "ID uživatele",
    COUNT(*) AS "Počet aktivit",
    COUNT(IF(a.typ = $0, 1, NULL)) AS "Systémové",
    COUNT(IF(a.typ = $9, 1, NULL)) AS "Brigádnické",
    COUNT(IF(a.typ = $1, 1, NULL)) AS "Deskovkové turnaje",
    COUNT(IF(a.typ = $2, 1, NULL)) AS "Larpy",
    COUNT(IF(a.typ = $3, 1, NULL)) AS "Přednášky",
    COUNT(IF(a.typ = $4  OR a.typ = $8, 1, NULL)) AS "RPG a LKD",
    COUNT(IF(a.typ = $5, 1, NULL)) AS "Dílny",
    COUNT(IF(a.typ = $6, 1, NULL)) AS "Wargaming",
    COUNT(IF(a.typ = $7, 1, NULL)) AS "Bonusy",
    SUM((UNIX_TIMESTAMP(a.konec) - UNIX_TIMESTAMP(a.zacatek)) / 3600) AS "Σ délka v hodinách"
FROM akce_prihlaseni p
JOIN akce_seznam a USING(id_akce)
WHERE a.rok=$rok
GROUP BY p.id_uzivatele
SQL,
    [
        0 => TypAktivity::SYSTEMOVA,
        9 => TypAktivity::BRIGADNICKA,
        1 => TypAktivity::TURNAJ_V_DESKOVKACH,
        2 => TypAktivity::LARP,
        3 => TypAktivity::PREDNASKA,
        4 => TypAktivity::RPG,
        8 => TypAktivity::LKD,
        5 => TypAktivity::WORKSHOP,
        6 => TypAktivity::WARGAMING,
        7 => TypAktivity::BONUS,
    ]
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['Σ cena']        = Uzivatel::zId($row['ID uživatele'])->finance()->cenaAktivity();
    $div                  = sprintf('%.3f', aktivityDiverzifikace(array_slice($row, 2, 8)));
    $row['Diverzifikace'] = $div;
    $data[]               = $row;
}

Report::zPole($data)->tFormat(get('format'));
