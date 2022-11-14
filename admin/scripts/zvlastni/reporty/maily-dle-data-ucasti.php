<?php

require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.email1_uzivatele
FROM uzivatele_hodnoty
LEFT JOIN r_uzivatele_zidle
    ON r_uzivatele_zidle.id_uzivatele = uzivatele_hodnoty.id_uzivatele
           AND r_uzivatele_zidle.id_zidle MOD 100 = -1 -- role (židle) se záporným ID končícím na 1 znamená "Přihlášen na GC NĚJAKÝ_ROK"
WHERE uzivatele_hodnoty.nechce_maily IS NULL
  AND uzivatele_hodnoty.email1_uzivatele LIKE '%@%'
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY MIN(COALESCE(r_uzivatele_zidle.id_zidle, 0)) -- nejzápornější židle je nejposlednější účast (Přihlášen na 2022 = ID židle -2201, Přihlášen na 2021 = ID židle -2101 ...), 0 je "Nikdy se na GC ještě nepřihlásil" a je proto v seznamu až dole
SQL
);

$report->tFormat(get('format'));
