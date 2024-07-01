<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
insert into reporty(skript, nazev, format_xlsx, format_html, viditelny) values ('finance-report-sirien', 'Sirienův rozpočtový report', 1, 1, 1)
SQL
);
