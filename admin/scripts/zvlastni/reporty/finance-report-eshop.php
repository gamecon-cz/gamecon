<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
SELECT
  `id_predmetu`,`nazev`,`model_rok`,`kod_predmetu`,`cena_aktualni`,`stav`,`nabizet_do`,`kusu_vyrobeno`,`typ`,`je_letosni_hlavni`,`ubytovani_den`,`popis`
FROM shop_predmety
ORDER BY model_rok DESC, id_predmetu DESC
SQL,
);

$report->tFormat(get('format'));
