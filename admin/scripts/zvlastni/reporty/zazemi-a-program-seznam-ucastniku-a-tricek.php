<?php
require __DIR__ . '/sdilene-hlavicky.php';

$tricko = \Gamecon\Shop\TypPredmetu::TRICKO;

$report = Report::zSql(<<<SQL
SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele, n.id_predmetu, p.nazev, p.model_rok
FROM uzivatele_hodnoty u
LEFT JOIN shop_nakupy n ON (n.id_uzivatele = u.id_uzivatele)
LEFT JOIN shop_predmety p ON (p.id_predmetu = n.id_predmetu)
WHERE (n.rok = $0) AND (p.typ = $tricko)
SQL
    , [0 => ROCNIK]
);

$report->tFormat(get('format'));
