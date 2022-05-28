<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE r_uzivatele_zidle_log (
    id_uzivatele INT NOT NULL,
    id_zidle INT NOT NULL,
    id_zmenil INT NULL,
    zmena VARCHAR(128) NOT NULL,
    kdy TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_zidle) REFERENCES r_zidle_soupis(id_zidle) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty(id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
)
SQL
);
