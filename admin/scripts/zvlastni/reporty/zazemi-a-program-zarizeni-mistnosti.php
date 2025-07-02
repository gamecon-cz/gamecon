<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik = $systemoveNastaveni->rocnik();

$report = Report::zSql(<<<SQL
SELECT akce_seznam.nazev_akce,
       akce_lokace.nazev AS mistnost,
       COALESCE(ostatni_akce_seznam.zacatek, akce_seznam.zacatek) AS zacatek,
       COALESCE(ostatni_akce_seznam.konec, akce_seznam.konec) AS konec,
       akce_seznam.vybaveni
FROM akce_seznam
LEFT JOIN akce_seznam AS ostatni_akce_seznam ON akce_seznam.patri_pod IS NOT NULL AND akce_seznam.patri_pod = ostatni_akce_seznam.patri_pod
LEFT JOIN akce_lokace ON akce_lokace.id_lokace = COALESCE(ostatni_akce_seznam.lokace, akce_seznam.lokace)
WHERE akce_seznam.vybaveni != '' AND akce_seznam.rok = {$rocnik}
SQL
);

$report->tFormat(get('format'));
