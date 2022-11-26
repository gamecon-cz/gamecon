<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO stranky
    SET url_stranky = 'o-aktivite-bez-typu',
        obsah = 'Každá aktivita by měla mít typ - to že má tento je špatně. Toto je pseudo typ existující jen proto, aby se systém nehroutil. Pokud nevíš tak zřejmě hledáš typ "technická"',
        poradi = 0
SQL
);

$this->q(<<<SQL
INSERT INTO akce_typy
    SET id_typu = 0, -- abychom odpružili "(bez typu – organizační)", který se ukládal s ID 0 které ale neexistovalo a pak to různě padalo
    typ_1p = '(bez typu – organizační)',
    typ_1pmn = '(bez typu – organizační)',
    url_typu_mn = 'organizacni',
    stranka_o = (SELECT id_stranky FROM stranky WHERE url_stranky = 'o-aktivite-bez-typu'),
    poradi = -1,
    mail_neucast = 0,
    popis_kratky = '',
    aktivni = 1,
    zobrazit_v_menu = 0
SQL
);
