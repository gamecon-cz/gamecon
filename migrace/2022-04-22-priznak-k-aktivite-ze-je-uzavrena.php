<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_seznam
ADD COLUMN uzavrena TINYINT(1) DEFAULT 0
SQL
);

$rok = ROK;
$this->q(<<<SQL
UPDATE akce_seznam
SET akce_seznam.uzavrena = 1
WHERE rok < {$rok}
SQL
);
