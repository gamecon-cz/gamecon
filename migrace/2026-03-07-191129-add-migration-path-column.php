<?php
/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->getCurrentDb();

// 1. Add .php suffix to migration_code values that have no file extension
// (historically, .php was stripped from basenames while .sql was kept)
// First, delete old rows that would collide with the renamed ones
$this->q(<<<SQL
    DELETE old_rows
    FROM migrations AS old_rows
    INNER JOIN migrations AS new_rows ON CONCAT(old_rows.migration_code, '.php') = new_rows.migration_code
    WHERE old_rows.migration_code NOT LIKE '%.php'
        AND old_rows.migration_code NOT LIKE '%.sql'
    SQL,
);
$this->q(<<<SQL
    UPDATE migrations
    SET migration_code = CONCAT(migration_code, '.php')
    WHERE migration_code NOT LIKE '%.php'
        AND migration_code NOT LIKE '%.sql'
    SQL,
);

// 2. Add migration_path column (if not exists)
$columnExists = $this->q(<<<SQL
    SELECT COUNT(*) AS cnt
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '{$dbName}'
        AND TABLE_NAME = 'migrations'
        AND COLUMN_NAME = 'migration_path'
    SQL,
)->fetch_assoc()['cnt'];

if ($columnExists > 0) {
    // Column already exists, but migration_code might still need dropping
    $codeColumnExists = $this->q(<<<SQL
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$dbName}'
            AND TABLE_NAME = 'migrations'
            AND COLUMN_NAME = 'migration_code'
        SQL,
    )->fetch_assoc()['cnt'];

    if ($codeColumnExists == 0) {
        return;
    }

    // Ensure migration_path is populated before dropping migration_code
    $this->q("UPDATE migrations SET migration_path = migration_code WHERE migration_path IS NULL");
    $this->q("ALTER TABLE migrations DROP PRIMARY KEY");
    $this->q("ALTER TABLE migrations DROP COLUMN migration_code");
    $this->q("ALTER TABLE migrations MODIFY COLUMN migration_path VARCHAR(256) NOT NULL");
    $this->q("ALTER TABLE migrations ADD PRIMARY KEY (migration_path)");

    return;
}

$this->q("ALTER TABLE migrations ADD COLUMN migration_path VARCHAR(256) DEFAULT NULL UNIQUE AFTER migration_code");

// 3. Backfill migration_path from files on disk
$migrationsDir = __DIR__;
$projectDir    = dirname(__DIR__);

// migrace/*.{php,sql}
foreach (glob($migrationsDir . '/*.{php,sql}', GLOB_BRACE) as $fileName) {
    $fileBaseName = basename($fileName);
    if (!preg_match('~^\d.+~', $fileBaseName)) {
        continue;
    }

    $migrationCode = dbQv(removeDiacritics($fileBaseName));
    $relativePath  = dbQv(removeDiacritics($fileBaseName));

    $this->q("UPDATE migrations SET migration_path = {$relativePath} WHERE migration_code = {$migrationCode} AND migration_path IS NULL");
}

// symfony/migrations/*/*.{php,sql}
$symfonyMigrationsDir = $projectDir . '/symfony/migrations';
if (is_dir($symfonyMigrationsDir)) {
    foreach (glob($symfonyMigrationsDir . '/*', GLOB_ONLYDIR) as $subDir) {
        $subDirName = basename($subDir);
        foreach (glob($subDir . '/*.{php,sql}', GLOB_BRACE) as $fileName) {
            $fileBaseName = basename($fileName);
            if (!preg_match('~^\d.+~', $fileBaseName)) {
                continue;
            }

            $migrationCode = dbQv(removeDiacritics($fileBaseName));
            $relativePath  = dbQv(removeDiacritics('symfony/' . $subDirName . '/' . $fileBaseName));

            $this->q("UPDATE migrations SET migration_path = {$relativePath} WHERE migration_code = {$migrationCode} AND migration_path IS NULL");
        }
    }
}

// 4. Catch-all for rows where files no longer exist on disk
$this->q("UPDATE migrations SET migration_path = migration_code WHERE migration_path IS NULL");

// 5. Drop migration_code column and make migration_path the primary key
$this->q("ALTER TABLE migrations DROP PRIMARY KEY");
$this->q("ALTER TABLE migrations DROP COLUMN migration_code");
$this->q("ALTER TABLE migrations MODIFY COLUMN migration_path VARCHAR(256) NOT NULL");
$this->q("ALTER TABLE migrations ADD PRIMARY KEY (migration_path)");
