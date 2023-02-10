<?php
/** @var \Godric\DbMigrations\Migration $this */

function rocnik_z_promenne_mysql($vychoziRocnik = ROCNIK) {
    $rocnik = dbFetchSingle(<<<SQL
SELECT @rocnik
SQL);
    return $rocnik ?: $vychoziRocnik;
}
