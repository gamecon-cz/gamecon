<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `akce_seznam`
DROP FOREIGN KEY `akce_seznam_ibfk_2`;

ALTER TABLE `akce_seznam`
    ADD CONSTRAINT `akce_seznam_ibfk_2` FOREIGN KEY (`patri_pod`) REFERENCES `akce_instance` (`id_instance`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
);
