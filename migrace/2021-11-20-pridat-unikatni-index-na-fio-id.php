<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby ADD UNIQUE INDEX (fio_id)
SQL
);
