<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS hromadne_akce_log (
    id_logu SERIAL,
    skupina VARCHAR(128),
    akce VARCHAR(255),
    vysledek VARCHAR(255),
    provedl INT NULL,
    kdy DATETIME NOT NULL DEFAULT NOW(),
    INDEX akce(akce),
    FOREIGN KEY FK_hromadne_akce_log_to_uzivatele_hodnoty(provedl) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE SET NULL
)
SQL
);
