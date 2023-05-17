<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
SELECT
  `id_predmetu`,`nazev`,`model_rok`,`cena_aktualni`,`stav`,`auto`,`nabizet_do`,`kusu_vyrobeno`,`typ`,`ubytovani_den`,`popis`
FROM shop_predmety
ORDER BY model_rok DESC, id_predmetu DESC
SQL,
);

$report->tFormat(get('format'));
