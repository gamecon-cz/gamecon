<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO reporty(skript, nazev, format_xlsx, format_html, viditelny)
VALUES
('stavy-uctu-ucastniku', 'Finance: Stavy účtů účastníků', 1, 1, 1)
SQL
);
