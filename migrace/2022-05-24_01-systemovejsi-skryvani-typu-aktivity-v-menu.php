<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_typy
ADD COLUMN zobrazit_v_menu TINYINT(1) DEFAULT 1
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET zobrazit_v_menu = 0 WHERE id_typu < 1
SQL
);
