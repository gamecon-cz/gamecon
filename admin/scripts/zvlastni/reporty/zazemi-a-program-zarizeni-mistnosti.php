<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik = $systemoveNastaveni->rocnik();

$report = Report::zSql(<<<SQL
SELECT akce_seznam.nazev_akce,
       (SELECT lokace.nazev
        FROM akce_lokace
        JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
        WHERE akce_lokace.id_akce = akce_seznam.id_akce
        ORDER BY akce_lokace.je_hlavni DESC,
                 akce_lokace.id_akce_lokace
        LIMIT 1) AS mistnost, -- hlavní místnost
       (SELECT GROUP_CONCAT(lokace.nazev ORDER BY akce_lokace.je_hlavni DESC, akce_lokace.id_akce_lokace SEPARATOR '; ')
        FROM akce_lokace
        JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
        WHERE akce_lokace.id_akce = akce_seznam.id_akce
        ) AS vsechny_mistnosti,
       akce_seznam.zacatek AS zacatek,
       akce_seznam.konec AS konec,
       akce_seznam.vybaveni
FROM akce_seznam
WHERE TRIM(akce_seznam.vybaveni) != ''
    AND akce_seznam.rok = {$rocnik}
    AND akce_seznam.patri_pod IS NULL -- hlavni akce

UNION ALL

SELECT akce_seznam.nazev_akce,
       (SELECT lokace.nazev
        FROM akce_lokace
        JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
        WHERE akce_lokace.id_akce = hlavni_akce.id_akce
        ORDER BY akce_lokace.je_hlavni DESC,
                 akce_lokace.id_akce_lokace
        LIMIT 1) AS mistnost, -- hlavní místnost
       (SELECT GROUP_CONCAT(lokace.nazev ORDER BY akce_lokace.je_hlavni DESC, akce_lokace.id_akce_lokace SEPARATOR '; ')
        FROM akce_lokace
        JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
        WHERE akce_lokace.id_akce = hlavni_akce.id_akce
        ) AS vsechny_mistnosti,
       akce_seznam.zacatek AS zacatek,
       akce_seznam.konec AS konec,
       hlavni_akce.vybaveni
FROM akce_seznam
INNER JOIN akce_instance -- INNER JOIN -> je to instance
    ON akce_seznam.patri_pod = akce_instance.id_instance
INNER JOIN akce_seznam AS hlavni_akce
    ON akce_instance.id_hlavni_akce = hlavni_akce.id_akce
WHERE TRIM(hlavni_akce.vybaveni) != ''
    AND hlavni_akce.rok = {$rocnik}
SQL
);

$report->tFormat(get('format'));
