<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby
ADD COLUMN pripsano_na_ucet_banky TIMESTAMP NULL DEFAULT NULL
SQL
);

$this->q(<<<SQL
UPDATE platby
    SET pripsano_na_ucet_banky = provedeno -- čas bude ponděkud se zpožděním oproti skutečnému připsání na účet, ale lepší než nic
SQL
);
