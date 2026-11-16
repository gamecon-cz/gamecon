<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The constraint alone would refuse an existing empty code without saying which role has it.
$bezKodu = $this->q(<<<'SQL'
    SELECT GROUP_CONCAT(id_role)
    FROM role_seznam
    WHERE kod_role = ''
    SQL)->fetchColumn();
if ($bezKodu !== null && $bezKodu !== false) {
    throw new \RuntimeException("Roles with an empty code, give them one before this migration: {$bezKodu}");
}

// Deploy migrations do not run in strict mode, which turns a missing code into an empty string
// instead of an error, so NOT NULL and the unique key alone do not catch it.
$this->q(<<<'SQL'
    ALTER TABLE role_seznam
        ADD CONSTRAINT CHK_kod_role_neprazdny CHECK (kod_role <> '')
    SQL);
