<?php

$this->q("
ALTER TABLE akce_typy
ADD mail_neucast tinyint(1) NOT NULL DEFAULT 0
COMMENT 'poslat mail účastníkovi, pokud nedorazí'
");

// povolit pro larpy, RPG, epic, wargaming, LKD
$this->q("
UPDATE akce_typy SET mail_neucast = 1 WHERE id_typu IN (2, 4, 11, 6, 8)
");
