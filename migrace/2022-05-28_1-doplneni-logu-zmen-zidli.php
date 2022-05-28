<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_uzivatele_zidle_log(id_uzivatele, id_zidle, id_zmenil, zmena,kdy)
SELECT id_uzivatele, id_zidle, posadil, 'posazen', posazen
    FROM r_uzivatele_zidle
SQL
);
