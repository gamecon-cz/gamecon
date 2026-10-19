<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */
$dbName = $this->q('SELECT DATABASE()')->fetchColumn();

$columnExists = function (string $table, string $column) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'",
    );

    return (int) $result->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Both answers belong to a year's registration, but legacy keeps them on the account, so
// declining once marked the customer forever. The legacy columns stay while the old form
// still reads them.

if (! $columnExists('shop_order', 'roommate')) {
    $this->q(<<<SQL
ALTER TABLE shop_order
    ADD roommate VARCHAR(255) DEFAULT NULL,
    ADD accommodation_declined TINYINT(1) DEFAULT NULL
SQL);
}

// This runner's q() goes through PDO::query(), which binds nothing, so the year is
// interpolated — it is an integer constant, not input.
$rocnik = ROCNIK;

// Backfill only the current year's orders: the account columns hold a single value with no
// year, so attributing it to any older order would be a guess.
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
