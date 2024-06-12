<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `uzivatele_role_log`
ADD COLUMN `id` SERIAL
SQL
);

$this->q(<<<SQL
ALTER TABLE `uzivatele_role`
ADD COLUMN `id` SERIAL
SQL
);
