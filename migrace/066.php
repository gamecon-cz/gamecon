<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE google_api_user_tokens (
    id INT UNSIGNED UNIQUE AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    google_client_id VARCHAR(128) NOT NULL,
    tokens TEXT NOT NULL,
    PRIMARY KEY user_id_google_client_id(user_id, google_client_id),
    CONSTRAINT FOREIGN KEY FK_google_api_user_tokens_to_uzivatele_hodnoty(user_id) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE InnoDB;

CREATE TABLE google_drive_dirs (
    id INT UNSIGNED UNIQUE AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    dir_id VARCHAR(128) PRIMARY KEY,
    original_name VARCHAR(64) NOT NULL,
    tag VARCHAR(128) NOT NULL DEFAULT '',
    UNIQUE KEY user_and_name(user_id, original_name),
    KEY tag(tag),
    CONSTRAINT FOREIGN KEY FK_google_drive_dirs_to_uzivatele_hodnoty(user_id) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE InnoDB
SQL
);
