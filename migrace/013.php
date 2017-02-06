<?php

$this->q("ALTER TABLE `uzivatele_hodnoty`
ADD `op` varchar(64) COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'číslo OP';");
