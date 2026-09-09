<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Deliberately not shop_nakupy_zrusene: that is an append-only audit log of real
// cancellations, and this row is overwritten every time a hotel night cancels breakfasts
// again, so only the most recent selection is offered back.

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS shop_snidane_snapshot (
    id_uzivatele BIGINT UNSIGNED NOT NULL,
    rok SMALLINT NOT NULL,
    variant_ids JSON NOT NULL,
    ulozeno DATETIME NOT NULL,
    PRIMARY KEY (id_uzivatele, rok),
    CONSTRAINT shop_snidane_snapshot_uzivatel
        FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
