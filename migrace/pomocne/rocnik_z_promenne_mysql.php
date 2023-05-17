<?php
/** @var \Godric\DbMigrations\Migration $this */

function rocnik_z_promenne_mysql(int $vychoziRocnik = ROCNIK) {
    $rocnik = dbFetchSingle(<<<SQL
SELECT @rocnik
SQL);
    return (int)$rocnik ?: $vychoziRocnik;
}
