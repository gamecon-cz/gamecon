<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE google_api_user_tokens (
    id INT UNSIGNED UNIQUE AUTO_INCREMENT,
    user_id INTEGER NOT NULL PRIMARY KEY,
    tokens TEXT NOT NULL,
    CONSTRAINT FOREIGN KEY FK_google_api_user_tokens_to_uzivatele_hodnoty(user_id) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE InnoDB;

CREATE TABLE google_spreadsheets (
    id INT UNSIGNED UNIQUE AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    spreadsheet_id VARCHAR(128) PRIMARY KEY,
    title TEXT NOT NULL,
    CONSTRAINT FOREIGN KEY FK_google_spreadsheets_to_uzivatele_hodnoty(user_id) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE InnoDB
SQL
);
