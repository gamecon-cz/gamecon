<?php

/** @var \Godric\DbMigrations\Migration $this */

$typColumnExists = $this->q(<<<SQL
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'shop_predmety'
  AND COLUMN_NAME = 'typ'
SQL,
)->fetch(PDO::FETCH_COLUMN);

if ($typColumnExists) {
    $this->q(<<<SQL
ALTER TABLE shop_predmety ADD COLUMN podtyp VARCHAR(255) DEFAULT NULL AFTER typ
SQL,
    );
} else {
    $this->q(<<<SQL
ALTER TABLE shop_predmety ADD COLUMN podtyp VARCHAR(255) DEFAULT NULL
SQL,
    );
}
