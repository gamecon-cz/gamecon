<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Discount rules become data an admin can read and edit.
//
// Today they are branches in Cenik keyed on a Pravo, with the numbers hardcoded in
// three places: quantities in Cenik and Finance, the meal amount and the bonus base in
// systemove_nastaveni, and the bonus threshold derived as 3 × the base. Nothing can
// answer "what discounts are in force" without reading PHP.
//
// The columns here are the ones an admin filters by and an audit queries. The varying
// part of each rule — scope, effect, quantity, threshold — lives in `parameters` as
// JSON, because the six rules genuinely have different shapes and as columns most
// would be NULL most of the time. App\Discount\DiscountParameters is the only way in,
// and rejects anything the scope and effect do not describe, including unknown keys.
//
// Rules key on a right (Pravo), not a role, because that is what Cenik checks — so a
// role gaining or losing a right keeps flowing through unchanged.

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS discount_rule (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    required_right INT NOT NULL,
    parameters LONGTEXT NOT NULL CHECK (JSON_VALID(parameters)),
    year SMALLINT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY UNIQ_discount_rule_code_year (code, year),
    KEY IDX_discount_rule_right (required_right),
    KEY IDX_discount_rule_year_active (year, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);

// The rule as it was applied, recorded on the purchase. discount_amount and
// discount_reason stay as the queryable summary; this is the full parameters plus the
// threshold as resolved at the time, so a later change to a rule cannot rewrite what
// somebody was charged. Legacy records nothing at all — all 71 709 rows have
// cena_nakupni equal to the catalogue price and no discount, because Cenik recomputes
// it on every read.
$columnExists = fn (string $table, string $column): bool => (bool) $this->q(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$column}'",
)->fetchColumn();

if (! $columnExists('shop_nakupy', 'discount_snapshot')) {
    $this->q(<<<SQL
ALTER TABLE shop_nakupy
    ADD COLUMN discount_snapshot LONGTEXT NULL DEFAULT NULL CHECK (discount_snapshot IS NULL OR JSON_VALID(discount_snapshot))
SQL);
}
