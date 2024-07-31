<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO reporty(skript, nazev, format_xlsx, format_html, viditelny)
VALUES
('role-podle-rocniku', 'Počty rolí platných v ročnících', 1, 1, 1)
SQL
);
