<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE uzivatele_slucovani_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_smazaneho_uzivatele INT NOT NULL,
    id_noveho_uzivatele INT NOT NULL,
    zustatek_smazaneho_puvodne INT NOT NULL,
    zustatek_noveho_puvodne INT NOT NULL,
    email_smazaneho VARCHAR(255) NOT NULL,
    email_noveho_puvodne VARCHAR(255) NOT NULL,
    zustatek_noveho_aktualne INT NOT NULL,
    email_noveho_aktualne VARCHAR(255) NOT NULL,
    kdy TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_smazany_uzivatel (id_smazaneho_uzivatele),
    KEY idx_novy_uzivatel (id_noveho_uzivatele),
    KEY idx_kdy (kdy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci
SQL,
);