<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
DROP PROCEDURE IF EXISTS `GDPR_PROCISTENI_UDAJU`
SQL,
);
