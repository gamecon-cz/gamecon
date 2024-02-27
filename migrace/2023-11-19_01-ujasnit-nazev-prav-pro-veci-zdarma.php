<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE r_prava_soupis
    SET jmeno_prava = 'Letošní placka zdarma'
    WHERE jmeno_prava = 'Placka zdarma'
SQL,
);

$this->q(<<<SQL
UPDATE r_prava_soupis
    SET jmeno_prava = 'Letošní kostka zdarma'
    WHERE jmeno_prava = 'Kostka zdarma'
SQL,
);

$this->q(<<<SQL
UPDATE role_seznam
SET popis_role = ''
WHERE nazev_role LIKE '% noc zdarma'
SQL,
);
