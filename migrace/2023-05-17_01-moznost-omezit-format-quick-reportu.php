<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE reporty_quick
    ADD COLUMN format_xlsx TINYINT(1) DEFAULT 1,
    ADD COLUMN format_html TINYINT(1) DEFAULT 1
SQL,
);

$this->q(<<<SQL
UPDATE reporty_quick
    SET format_xlsx = 0
WHERE dotaz COLLATE utf8_czech_ci LIKE '%JSON_OBJECT%'
SQL,
);
