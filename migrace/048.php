<?php
// smazání hodnoty občanský průkaz u všech uživatelů

/** @var \Godric\DbMigrations\Migration $this */
$this->q("
UPDATE `uzivatele_hodnoty` SET `op`= '' 
");