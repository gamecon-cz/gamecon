<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE obchod_bunky ADD barva_text VARCHAR(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NULL DEFAULT NULL AFTER barva;
SQL,
);
