<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE systemove_nastaveni_log (
    id_nastaveni_log SERIAL,
    id_uzivatele INT,
    id_nastaveni BIGINT UNSIGNED NOT NULL,
    hodnota VARCHAR(256) NOT NULL,
    kdy TIMESTAMP /* MySQL 5.5 nedovoluje DEFAULT pro DATETIME, https://stackoverflow.com/questions/49009054/invalid-default-value-for-current-timestamp-field-in-live-server */ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY systemove_nastaveni_log_to_systemove_nastaveni (id_nastaveni) REFERENCES systemove_nastaveni (id_nastaveni) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY systemove_nastaveni_log_to_uzivatele_hodnoty (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON UPDATE CASCADE ON DELETE SET NULL
)
SQL
);
