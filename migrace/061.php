<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
DROP FOREIGN KEY r_uzivatele_zidle_ibfk_1,
ADD FOREIGN KEY FK_r_uzivatele_zidle_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
ON DELETE CASCADE
ON UPDATE CASCADE
SQL
);
