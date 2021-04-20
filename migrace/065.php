<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE akce_import(
    id_akce_import SERIAL,
    id_uzivatele INT NOT NULL,
    google_sheet_id VARCHAR(128) NOT NULL,
    cas DATETIME NOT NULL,
    KEY google_sheet_id(google_sheet_id),
    FOREIGN KEY FK_akce_import_to_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE NO ACTION
);
SQL
);
