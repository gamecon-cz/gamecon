<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Booking a night whose price already includes breakfast cancels a breakfast bought
// separately for that morning. Legacy deletes those rows, so a customer who changes their
// mind back has nothing to restore and no way to know what they had. This records the last
// breakfast selection so it can be offered back.
//
// One row per customer and year, rewritten whenever breakfasts are picked again: the offer
// is "put back what you had", so only the most recent selection is worth keeping. It is
// deliberately not shop_nakupy_zrusene, which is an append-only audit log of real
// cancellations and must not be overwritten.

$this->q(<<<SQL
CREATE TABLE shop_snidane_snapshot (
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
