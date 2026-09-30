<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

// The flag the import reads back: 1 only for the item each rule names as this year's.
$letosniKody = array_values(array_filter(array_column(
    (new \Gamecon\Shop\LetosniPredmetyZdarma($systemoveNastaveni->rocnik()))->stav(),
    'kod',
)));

$report = Report::zSql(<<<SQL
SELECT
  `id_predmetu`,`nazev`,`model_rok`,`kod_predmetu`,`cena_aktualni`,`stav`,`nabizet_do`,`kusu_vyrobeno`,`typ`,
  IF(archived_at IS NULL AND kod_predmetu IN ($0), 1, 0) AS `je_letosni_hlavni`,
  `ubytovani_den`,`popis`,`vedlejsi`,`breakfast_included` AS `snidane_v_cene`
FROM shop_predmety_s_typem AS shop_predmety
ORDER BY model_rok DESC, id_predmetu DESC
SQL,
    [
        0 => $letosniKody,
    ],
);

$report->tFormat(get('format'));
