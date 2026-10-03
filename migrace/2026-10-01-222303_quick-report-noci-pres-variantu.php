<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The quick report of food and lodging per (half-)organizer counts nights by day; a night's
// purchase will point at its room type, so the day is read from the purchased variant.
$puvodniSpojeni = 'join shop_predmety_s_typem sp on sp.id_predmetu = sn.id_predmetu';
$noveSpojeni = 'join shop_varianty_s_typem sp on sp.id_varianty = sn.variant_id';

// `q()` takes no bound parameters, and the stored SQL is quoted through PDO.
$this->q(
    'UPDATE reporty_quick SET dotaz = REPLACE(dotaz, ' . $this->connection->quote($puvodniSpojeni)
    . ', ' . $this->connection->quote($noveSpojeni) . ')'
    . ' WHERE nazev = ' . $this->connection->quote('Sirienovo jídlo a ubytování (půl)orgů'),
);
