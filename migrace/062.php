<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE google_api_user_tokens (
    id INT UNSIGNED UNIQUE AUTO_INCREMENT,
    user_id INTEGER NOT NULL PRIMARY KEY,
    token TEXT NOT NULL,
    CONSTRAINT FOREIGN KEY FK_user_id(user_id) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE InnoDB
SQL
);
