<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS uzivatele_hromadne_akce_log
    (id_logu SERIAL, skupina VARCHAR(128), akce VARCHAR(255), vysledek VARCHAR(255), kdy DATETIME NOT NULL DEFAULT NOW(), INDEX akce(akce))
SQL
);
