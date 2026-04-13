<?php

/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

// Check if text column is still int (FK to texty) or already longtext
$columnResult = $this->q(
    "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'novinky' AND COLUMN_NAME = 'text'",
);
$dataType = $columnResult->fetch(\PDO::FETCH_ASSOC)['DATA_TYPE'] ?? '';

if ($dataType === 'longtext') {
    return; // Already migrated
}

// Drop FK constraint if it exists (blocks column type change)
$this->dropForeignKeysIfExist(['FK_novinky_to_texty'], 'novinky');

$this->q("ALTER TABLE novinky CHANGE `text` `text` longtext NOT NULL COMMENT 'markdown'");
