<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_nakupy
ADD COLUMN id_objednatele INT NULL DEFAULT NULL AFTER id_uzivatele,
ADD FOREIGN KEY (id_objednatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE SET NULL
SQL,
);
