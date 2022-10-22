<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO akce_typy
    SET id_typu = 102,
    typ_1p = 'brigádnická',
    typ_1pmn = 'brigádnické',
    url_typu_mn = 'brigadnicke',
    stranka_o = (SELECT id_stranky FROM stranky WHERE url_stranky = 'o-aktivite-bez-typu'),
    poradi = -3,
    mail_neucast = 0,
    popis_kratky = 'Placená výpomoc Gameconu',
    aktivni = 1,
    zobrazit_v_menu = 0
SQL
);
