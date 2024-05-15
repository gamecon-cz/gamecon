<?php
$this->q(<<<SQL
UPDATE `reporty`
    SET viditelny = 0
    WHERE skript = 'bfgr-report';
SQL
);
