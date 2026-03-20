<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q("DELETE FROM reporty_prava WHERE id_reportu = (SELECT id FROM reporty WHERE skript = 'update-zustatku')");
$this->q("UPDATE reporty SET viditelny = 0 WHERE skript = 'update-zustatku'");
$this->q("UPDATE reporty SET viditelny = 0 WHERE skript = 'finance-report-eshop'");
