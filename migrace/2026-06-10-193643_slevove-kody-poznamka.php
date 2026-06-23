<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

$this->q('
ALTER TABLE slevove_kody
    ADD COLUMN IF NOT EXISTS poznamka VARCHAR(255) NULL DEFAULT NULL AFTER invalidated
');
