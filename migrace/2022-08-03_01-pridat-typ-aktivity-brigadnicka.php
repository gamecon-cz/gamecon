<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy
    SET id_typu = 102,
    typ_1p = 'brigádnícká',
    typ_1pmn = 'brigádnícké',
    url_typu_mn = 'brigadnicke',
    stranka_o = 0,
    poradi = -3,
    mail_neucast = 0,
    popis_kratky = 'Placená výpomoc Gameconu',
    aktivni = 1,
    zobrazit_v_menu = 0
SQL
);
