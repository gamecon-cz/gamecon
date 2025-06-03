<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
$rocnik             = $systemoveNastaveni->rocnik();

$report = Report::zSql(<<<SQL
SELECT nazev_akce, zacatek
FROM akce_seznam
WHERE bez_slevy = 1 AND rok = {$rocnik}
SQL,
);
$report->tFormat(get('format'));
