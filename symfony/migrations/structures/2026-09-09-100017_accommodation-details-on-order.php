<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Who you share a room with, and whether you want a room at all, are answers to a given
// year's registration — but legacy keeps both on the user account, so declining once marks
// the customer forever and last year's roommate silently carries over. Moving them onto the
// order scopes them to their year. The legacy columns stay: the old form still reads them,
// and the new endpoint writes both until it is gone.

$this->q(<<<SQL
ALTER TABLE shop_order
    ADD roommate VARCHAR(255) DEFAULT NULL,
    ADD accommodation_declined TINYINT(1) DEFAULT NULL
SQL);

// Backfill only the current year's orders: the account columns hold a single value with no
// year, so attributing it to any older order would be a guess.
$rocnik = ROCNIK;

$this->q(<<<SQL
UPDATE shop_order
JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = shop_order.customer_id
SET shop_order.roommate = NULLIF(TRIM(uzivatele_hodnoty.ubytovan_s), ''),
    shop_order.accommodation_declined = uzivatele_hodnoty.nechce_ubytovani
WHERE shop_order.year = {$rocnik}
SQL);

// Orders of past years never had an answer of their own, so they get the neutral one rather
// than this year's.
$this->q(<<<SQL
UPDATE shop_order
SET accommodation_declined = 0
WHERE accommodation_declined IS NULL
SQL);

$this->q(<<<SQL
ALTER TABLE shop_order
    MODIFY accommodation_declined TINYINT(1) NOT NULL
SQL);
