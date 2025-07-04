<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik = $systemoveNastaveni->rocnik();

$report = Report::zSql(<<<SQL
SELECT akce_seznam.nazev_akce,
       akce_lokace.nazev AS mistnost,
       akce_seznam.zacatek,
       akce_seznam.konec
FROM akce_seznam
LEFT JOIN akce_lokace ON akce_lokace.id_lokace = akce_seznam.lokace
WHERE akce_seznam.rok = {$rocnik}
GROUP BY akce_seznam.id_akce, akce_seznam.zacatek
ORDER BY akce_seznam.zacatek
SQL
);
$report->tFormat(get('format'));
