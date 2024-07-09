<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
select uh.id_uzivatele id, uh.login_uzivatele login, uh.jmeno_uzivatele jmeno, uh.prijmeni_uzivatele prijmeni, concat('<a href="../infopult/potvrzeni-rodicu?id=', uh.id_uzivatele, '">potvrzení</a>') odkaz, uh.potvrzeni_zakonneho_zastupce_soubor nahrano_kdy
from uzivatele_hodnoty uh
where uh.potvrzeni_zakonneho_zastupce_soubor is not null
  and (uh.potvrzeni_zakonneho_zastupce is null or date(uh.potvrzeni_zakonneho_zastupce_soubor) > uh.potvrzeni_zakonneho_zastupce)
SQL,
);

$report->tFormat(get('format'));
