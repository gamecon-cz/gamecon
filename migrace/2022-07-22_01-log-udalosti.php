<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE log_udalosti(
    id_udalosti SERIAL,
    id_logujiciho INT NOT NULL,
    zprava VARCHAR(255),
    metadata VARCHAR(255),
    rok INT UNSIGNED NOT NULL,
    FOREIGN KEY (id_logujiciho) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE NO ACTION,
    INDEX (metadata)
)
SQL
);
