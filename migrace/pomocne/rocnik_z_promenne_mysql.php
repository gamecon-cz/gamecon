<?php
/** @var \Godric\DbMigrations\Migration $this */

function rocnik_z_promenne_mysql($vychoziRocnik = ROK) {
    $rocnik = dbFetchSingle(<<<SQL
SELECT @rocnik
SQL);
    return $rocnik ?: $vychoziRocnik;
}
