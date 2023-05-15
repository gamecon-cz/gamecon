<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Shop\TypPredmetu;

$typVstupne = TypPredmetu::VSTUPNE;

foreach (range(2016, 2022) as $rocnik) {
    $lonskyRocnik = $rocnik - 1;
    $this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'PRUMERNE_LONSKE_VSTUPNE',
    hodnota = COALESCE(
        (SELECT SUM(cena_nakupni) / COUNT(*) FROM shop_nakupy JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu AND shop_predmety.typ = $typVstupne WHERE rok = $lonskyRocnik AND cena_nakupni > 0),
        0.0
    ),
    vlastni = 1,
    datovy_typ = 'number',
    nazev = 'Průměrné loňské vstupné',
    popis = 'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného',
    zmena_kdy = NOW(),
    skupina = 'Finance',
    poradi = (SELECT MAX(poradi) FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni = 1,
    rocnik_nastaveni = $rocnik
SQL,
    );
}
