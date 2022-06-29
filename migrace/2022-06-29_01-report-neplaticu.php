<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO reporty(skript, nazev, format_xlsx, format_html)
VALUES
('finance-neplatici', 'Finance: Neplatiči k odhlášení', 1, 1)
SQL
);
