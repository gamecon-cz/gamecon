<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO stranky
    SET id_stranky = 165,
        url_stranky = 'o-aktivite-bez-typu',
        obsah = 'Každá aktivita by měla mít typ - to že má tento je špatně. Toto je pseudo typ existující jen proto, aby se systém nehroutil. Pokud nevíš tak zřejmě hledáš typ "technická"',
        poradi = 0
SQL
);

$this->q(<<<SQL
INSERT INTO akce_typy
    SET id_typu = 102,
    typ_1p = 'brigádnická',
    typ_1pmn = 'brigádnické',
    url_typu_mn = 'brigadnicke',
    stranka_o = 165,
    poradi = -3,
    mail_neucast = 0,
    popis_kratky = 'Placená výpomoc Gameconu',
    aktivni = 1,
    zobrazit_v_menu = 0
SQL
);
