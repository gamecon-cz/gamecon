<?php

// Přidání tabulky pro medailonky vypravěčů

$this->q("

ALTER TABLE `stranky`
ADD `poradi` tinyint NOT NULL;

");
