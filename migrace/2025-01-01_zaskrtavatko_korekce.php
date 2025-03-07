<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_seznam
ADD COLUMN probehla_korekce tinyint(1) NOT NULL DEFAULT '0'
SQL,
);

$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES (1034, 'Provádí korekce', 'Může nastavit checkbox u aktivity o provedení korekce.')
SQL,
);
