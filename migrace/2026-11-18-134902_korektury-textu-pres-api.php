<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// An activity text sent to the AI through the API (Message Batches) waits for the batch to end:
// the batch ID tells which rows the batch answers, the time shows the proofreader how long it has been waiting.
// Both are cleared when nothing waits for the batch any more, so a text without an answer can go out again.
// The batch ID is case-sensitive, hence utf8mb4_bin (the other binary collation CollationTest allows).
$this->q(<<<'SQL'
ALTER TABLE akce_korektura
    ADD COLUMN IF NOT EXISTS id_davky_ai     VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL AFTER zmeny,
    ADD COLUMN IF NOT EXISTS odeslano_ai_kdy TIMESTAMP    NULL DEFAULT NULL AFTER id_davky_ai,
    ADD KEY IF NOT EXISTS id_davky_ai (id_davky_ai)
SQL,
);
