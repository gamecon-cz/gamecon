<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE uzivatele_role_podle_rocniku (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_uzivatele INT NOT NULL,
    id_role INT NOT NULL,
    od_kdy DATETIME NOT NULL,
    rocnik INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci
SQL
);
