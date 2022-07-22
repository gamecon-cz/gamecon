<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
ADD COLUMN id_zmenil INT NULL,
ADD FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty(id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
SQL
);
