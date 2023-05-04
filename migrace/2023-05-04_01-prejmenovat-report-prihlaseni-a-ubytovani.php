<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev= 'Nepřihlášení a neubytovaní vypravěči + další',
    format_xlsx = 1
WHERE skript = 'neprihlaseni-vypraveci'
SQL,
);

$this->q(<<<SQL
UPDATE r_prava_soupis
SET jmeno_prava = 'Reporty - zahrnout do reportu "Nepřihlášení a neubytovaní"'
WHERE jmeno_prava = 'Report neubytovaných'
SQL,
);
