<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS uzivatele_url(
    id_url_uzivatele SERIAL,
    id_uzivatele INT NOT NULL,
    url VARCHAR(255) NOT NULL PRIMARY KEY,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON UPDATE CASCADE ON DELETE CASCADE
)
SQL
);

foreach (Uzivatel::vsichni() as $uzivatel) {
    $url = $uzivatel->url();
    if ($url !== null) {
        $this->q(<<<SQL
INSERT IGNORE INTO uzivatele_url (id_uzivatele, url)
VALUES ({$uzivatel->id()}, '$url')
SQL
        );
    }
}
