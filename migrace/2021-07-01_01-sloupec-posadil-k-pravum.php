<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle ADD COLUMN posadil INT,
    ADD FOREIGN KEY (posadil) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
);
