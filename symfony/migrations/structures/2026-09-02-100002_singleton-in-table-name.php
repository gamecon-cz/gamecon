<?php

/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

// Helper: check if a table exists
$tableExists = function (string $table) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: check if an index exists
$indexExists = function (string $table, string $indexName) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND INDEX_NAME = '$indexName'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: check if a FK exists
$fkExists = function (string $table, string $constraintName) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = '$constraintName' AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Rename google_api_user_tokens → google_api_user_token
if ($tableExists('google_api_user_tokens') && !$tableExists('google_api_user_token')) {
    $this->q("RENAME TABLE google_api_user_tokens TO google_api_user_token");
}

// Rename google_drive_dirs → google_drive_dir
if ($tableExists('google_drive_dirs') && !$tableExists('google_drive_dir')) {
    $this->q("RENAME TABLE google_drive_dirs TO google_drive_dir");
}

// Rename indexes (only if the tables exist)
if ($tableExists('google_api_user_token')) {
    if ($indexExists('google_api_user_token', 'idx_9a526eb4a76ed395') && !$indexExists('google_api_user_token', 'IDX_E2B772D4A76ED395')) {
        $this->q("ALTER TABLE google_api_user_token RENAME INDEX idx_9a526eb4a76ed395 TO IDX_E2B772D4A76ED395");
    }
}

if ($tableExists('google_drive_dir')) {
    if ($indexExists('google_drive_dir', 'idx_9e13beafa76ed395') && !$indexExists('google_drive_dir', 'IDX_78417C52A76ED395')) {
        $this->q("ALTER TABLE google_drive_dir RENAME INDEX idx_9e13beafa76ed395 TO IDX_78417C52A76ED395");
    }
}

// Update product_product_tag FK constraints to remove ON DELETE CASCADE
// (change to default behavior — no cascade action)
if ($tableExists('product_product_tag')) {
    if ($fkExists('product_product_tag', 'FK_4F897D834584665A')) {
        $this->q("ALTER TABLE product_product_tag DROP FOREIGN KEY FK_4F897D834584665A");
        $this->q("ALTER TABLE product_product_tag ADD CONSTRAINT FK_4F897D834584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu)");
    }
    if ($fkExists('product_product_tag', 'FK_4F897D83BAD26311')) {
        $this->q("ALTER TABLE product_product_tag DROP FOREIGN KEY FK_4F897D83BAD26311");
        $this->q("ALTER TABLE product_product_tag ADD CONSTRAINT FK_4F897D83BAD26311 FOREIGN KEY (tag_id) REFERENCES product_tag (id)");
    }
}
