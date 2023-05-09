<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$rocnik = $systemoveNastaveni->rok();

$nejakePredmety = static fn(int $rocnikSql) => dbFetchSingle(<<<SQL
SELECT EXISTS(SELECT * FROM shop_predmety WHERE model_rok = '$rocnikSql')
SQL,
);

while (!$nejakePredmety($rocnik) && $rocnik > 2010) {
    $rocnik--;
}

$report = Report::zSql(<<<SQL
SELECT
  `model_rok`,`nazev`,`cena_aktualni`,`stav`,`auto`,`nabizet_do`,`kusu_vyrobeno`,`typ`,`ubytovani_den`,`popis`
FROM shop_predmety
WHERE model_rok = '$rocnik'
SQL,
);

$report->tFormat(get('format'));
