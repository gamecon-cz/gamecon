<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD COLUMN vyznam VARCHAR(48) DEFAULT NULL
SQL
);

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET vyznam = REGEXP_REPLACE(kod_zidle, 'GC[0-9]+_', '')
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
MODIFY COLUMN vyznam VARCHAR(48) NOT NULL,
    ADD INDEX (vyznam)
SQL
);
