<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik = $systemoveNastaveni->rocnik();

$report = Report::zSql(<<<SQL
SELECT akce_seznam.nazev_akce,
       (SELECT lokace.nazev
        FROM lokace
        WHERE lokace.id_lokace = COALESCE(
            akce_seznam.id_hlavni_lokace,
            (SELECT akce_lokace.id_lokace FROM akce_lokace WHERE akce_lokace.id_akce = akce_seznam.id_akce ORDER BY akce_lokace.id_lokace LIMIT 1)
        )
        LIMIT 1) AS mistnost, -- hlavní místnost
       (SELECT GROUP_CONCAT(lokace.nazev ORDER BY akce_lokace.id_lokace SEPARATOR '; ')
        FROM akce_lokace
        JOIN lokace ON akce_lokace.id_lokace = lokace.id_lokace
        WHERE akce_lokace.id_akce = akce_seznam.id_akce
        ) AS vsechny_mistnosti,
       akce_seznam.zacatek,
       akce_seznam.konec
FROM akce_seznam
WHERE akce_seznam.rok = {$rocnik}
GROUP BY akce_seznam.id_akce, akce_seznam.zacatek
ORDER BY akce_seznam.zacatek
SQL
);
$report->tFormat(get('format'));
