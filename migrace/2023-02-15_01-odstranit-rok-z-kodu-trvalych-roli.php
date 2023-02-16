<?php
/** @var \Godric\DbMigrations\Migration $this */

/** oprava chyby z 2023-01-30_01-kody-zidli.php */
$this->q(<<<SQL
UPDATE role_seznam
    SET kod_role = REGEXP_REPLACE(kod_role, '^GC[0-9]{4}_', '')
WHERE typ_role = 'trvala'
SQL
);
