<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET skupina = CASE skupina
                  WHEN 'cas' THEN 'Časy'
                  WHEN 'finance' THEN 'Finance'
                  ELSE CONVERT(skupina USING utf8) END
SQL
);
