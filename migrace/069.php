<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO reporty(skript, nazev, format_csv, format_html, viditelny)
VALUES ('ubytovani', 'Ubytování', 1, 1, 0 /* na strance s reporty neviditelny, ale dostupny pres primy odkaz ve Financich */);
SQL
);
