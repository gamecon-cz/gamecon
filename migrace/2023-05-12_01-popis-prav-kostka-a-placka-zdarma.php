<?php

$popis = 'Jednu %s "letošní model" si může objednat za 0.- ("letošní" je ta, kterou jsme v předchozích ročnících neprodali ani jednu, je nejnověší model_rok, je nejdražší a byla zadána do systému jako poslední)';

$popisPlacka = sprintf($popis, 'placku');
$popisKostka = sprintf($popis, 'kostku');

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE r_prava_soupis
    SET popis_prava = CASE
        WHEN jmeno_prava = 'Placka zdarma' THEN '{$popisPlacka}'
        WHEN jmeno_prava = 'Kostka zdarma' THEN '{$popisKostka}'
        ELSE popis_prava
    END
WHERE jmeno_prava IN ('Placka zdarma', 'Kostka zdarma')
SQL,
);
