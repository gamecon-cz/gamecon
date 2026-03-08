<?php

namespace Godric\DbMigrations;

define('INITIAL_MIGRATION_HASH', 'initial_migration_hash');
define('LAST_APPLIED_MIGRATION_ID', 'last_applied_migration_id');
define('LATEST_MIGRATION_HASH', 'latest_migration_hash');

class DbMigrations
{

    private Backups            $backups;
    private DbMigrationsConfig $config;
    private \mysqli            $connection;
    private                    $migrations;
    private readonly ?WebGui   $webGui;
    private                    $hasTableMigrationsV2    = null;
    private                    $hasTableMigrationsV1    = null;
    private ?bool              $hasMigrationPathColumn  = null;
    private ?array             $unappliedMigrations     = null;

    public function __construct(DbMigrationsConfig $conf)
    {
        $this->config = $conf;

        $this->connection = $this->config->getConnection();
        $this->backups    = new Backups($this->connection, $this->config->getBackupsDirectory());
        $this->webGui     = $this->config->useWebGui()
            ? new WebGui()
            : null;
    }

    public function getWebGui(): ?WebGui
    {
        return $this->webGui;
    }

    public function hasUnappliedMigrations(): bool
    {
        return (bool)$this->getUnappliedMigrations();
    }

    private function handleUnappliedMigrations(bool $silent): void
    {
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration, $silent || $migration->isEndless());
        }
    }

    private function handleEndlessMigrations(bool $hasUnappliedOneTimeMigrations): void
    {
        foreach ($this->getEndlessMigrations($hasUnappliedOneTimeMigrations) as $migration) {
            $this->apply($migration, true);
        }
    }

    /**
     * @return Migration[]
     */
    private function getUnappliedMigrations(): array
    {
        if (!isset($this->unappliedMigrations)) {
            $migrations = $this->getNormalMigrations();
            if (!$migrations) {
                return [];
            }

            if (!$this->hasTableMigrationsForV2()) {
                if (!$this->hasTableMigrationsForV1()) {
                    return $migrations;
                }
                $migrationsV1          = $this->getMigrationsV1($migrations);
                $unappliedMigrationsV1 = $this->getUnappliedMigrationsV1($migrationsV1);
                $migrationsV2          = $this->getMigrationsV2($migrations);

                return array_merge($unappliedMigrationsV1, $migrationsV2);
            }

            if ($this->hasMigrationPathColumn()) {
                $migrationPaths = array_map(static function (
                    Migration $migration,
                ) {
                    return $migration->getRelativePath();
                }, $migrations);

                $this->connection->query("CREATE TEMPORARY TABLE known_migration_paths_tmp (migration_path VARCHAR(256) PRIMARY KEY)");
                $migrationPathsSql = implode(
                    ',',
                    array_map(
                        static function (
                            $migrationPath,
                        ) {
                            $escapedPath = dbQv($migrationPath);

                            return "($escapedPath)";
                        },
                        $migrationPaths,
                    ),
                );
                $this->connection->query("INSERT INTO known_migration_paths_tmp (migration_path) VALUES $migrationPathsSql");

                $query = $this->connection->query(
                    "SELECT known_migration_paths_tmp.migration_path
FROM known_migration_paths_tmp
LEFT JOIN migrations ON migrations.migration_path = known_migration_paths_tmp.migration_path
WHERE migrations.migration_id IS NULL",
                );

                $wrappedUnappliedPaths = $query->fetch_all();

                $this->connection->query("DROP TEMPORARY TABLE known_migration_paths_tmp");

                $unappliedPaths = array_column($wrappedUnappliedPaths, 0);

                $this->unappliedMigrations = array_filter(
                    $migrations,
                    static fn(
                        Migration $migration,
                    ) => in_array($migration->getRelativePath(), $unappliedPaths, true),
                );
            } else {
                // Fallback for old schema without migration_path column
                $migrationCodes = array_map(static function (
                    Migration $migration,
                ) {
                    return $migration->getCode();
                }, $migrations);

                $this->connection->query("CREATE TEMPORARY TABLE known_migration_codes_tmp (migration_code VARCHAR(128) PRIMARY KEY)");
                $migrationCodesSql = implode(
                    ',',
                    array_map(
                        static function (
                            $migrationCode,
                        ) {
                            return "(" . dbQv($migrationCode) . ")";
                        },
                        $migrationCodes,
                    ),
                );
                $this->connection->query("INSERT INTO known_migration_codes_tmp (migration_code) VALUES $migrationCodesSql");

                $query = $this->connection->query(
                    "SELECT known_migration_codes_tmp.migration_code
FROM known_migration_codes_tmp
LEFT JOIN migrations ON migrations.migration_code = known_migration_codes_tmp.migration_code
    OR CONCAT(migrations.migration_code, '.php') = known_migration_codes_tmp.migration_code
WHERE migrations.migration_id IS NULL",
                );

                $wrappedUnappliedCodes = $query->fetch_all();

                $this->connection->query("DROP TEMPORARY TABLE known_migration_codes_tmp");

                $unappliedCodes = array_column($wrappedUnappliedCodes, 0);

                $this->unappliedMigrations = array_filter(
                    $migrations,
                    static fn(
                        Migration $migration,
                    ) => in_array($migration->getCode(), $unappliedCodes, false),
                );
            }
        }

        return $this->unappliedMigrations;
    }

    /**
     * @param Migration[] $migrations
     * @return Migration[]
     */
    private function getMigrationsV1(array $migrations): array
    {
        return array_filter($migrations, static function (
            Migration $migration,
        ) {
            return $migration->getVersion() === 1;
        });
    }

    /**
     * @param Migration[] $migrationsV1
     * @return Migration[]
     */
    private function getUnappliedMigrationsV1(array $migrationsV1): array
    {
        $idOfLastAppliedMigrationV1 = $this->getIdOfLastAppliedMigrationV1();

        return array_filter($migrationsV1, static function (
            Migration $migration,
        ) use
        (
            $idOfLastAppliedMigrationV1,
        ) {
            return $migration->getId() > $idOfLastAppliedMigrationV1;
        });
    }

    private function getIdOfLastAppliedMigrationV1(): int
    {
        $query                          = $this->connection->query(<<<SQL
SELECT value FROM db_migrations WHERE name = 'last_applied_migration_id'
SQL,
        );
        $lastAppliedMigrationSerialized = $query->fetch_row()[0] ?? false;

        return $lastAppliedMigrationSerialized !== false
            ? unserialize($lastAppliedMigrationSerialized)
            : -1;
    }

    /**
     * @param Migration[] $migrations
     * @return Migration[]
     */
    private function getMigrationsV2(array $migrations): array
    {
        return array_filter($migrations, static function (
            Migration $migration,
        ) {
            return $migration->getVersion() === 2;
        });
    }

    private function hasTableMigrationsForV2(): bool
    {
        if ($this->hasTableMigrationsV2 === true) {
            return true;
        }
        $this->hasTableMigrationsV2 = count($this->connection->query("SHOW TABLES LIKE 'migrations'")->fetch_all()) > 0;

        return $this->hasTableMigrationsV2;
    }

    private function hasTableMigrationsForV1(): bool
    {
        if ($this->hasTableMigrationsV1 === true) {
            return true;
        }
        $this->hasTableMigrationsV1 = count($this->connection->query("SHOW TABLES LIKE 'db_migrations'")->fetch_all()) > 0;

        return $this->hasTableMigrationsV1;
    }

    private function hasMigrationPathColumn(): bool
    {
        if ($this->hasMigrationPathColumn === null) {
            $this->hasMigrationPathColumn = $this->hasTableMigrationsForV2()
                && count($this->connection->query("SHOW COLUMNS FROM migrations LIKE 'migration_path'")->fetch_all()) > 0;
        }

        return $this->hasMigrationPathColumn;
    }

    /**
     * @return Migration[]
     */
    private function getMigrations(): array
    {
        if (!is_array($this->migrations)) {
            $migrations = [];
            foreach (glob($this->config->getMigrationsDirectory() . '/*.{php,sql}', GLOB_BRACE) as $fileName) {
                $fileBaseName = basename($fileName);
                if (!preg_match('~^\d.+~', $fileBaseName)) {
                    continue;
                }

                $relativePath             = $fileBaseName;
                $migrations[$relativePath] = new Migration(
                    $fileName,
                    $fileBaseName,
                    $this->connection,
                    $relativePath,
                );
            }

            // Load migrations from symfony/migrations subdirectories
            $symfonyMigrationsDir = dirname(__DIR__, 4) . '/symfony/migrations';
            assert(is_dir($symfonyMigrationsDir), sprintf('Dir %s does not exist', $symfonyMigrationsDir));
            foreach (glob($symfonyMigrationsDir . '/*', GLOB_ONLYDIR) as $subDir) {
                $subDirName = basename($subDir);
                foreach (glob($subDir . '/*.{php,sql}', GLOB_BRACE) as $fileName) {
                    $fileBaseName = basename($fileName);
                    if (!preg_match('~^\d.+~', $fileBaseName)) {
                        continue;
                    }

                    $relativePath             = 'symfony/' . $subDirName . '/' . $fileBaseName;
                    $migrations[$relativePath] = new Migration(
                        $fileName,
                        $fileBaseName,
                        $this->connection,
                        $relativePath,
                    );
                }
            }

            uasort($migrations, static fn(
                Migration $a,
                Migration $b,
            ) => strcmp($a->getCode(), $b->getCode()));

            $this->migrations = array_values($migrations);
        }

        return $this->migrations;
    }

    private function getNormalMigrations(): array
    {
        return array_filter($this->getMigrations(), static fn(
            Migration $migration,
        ) => !$migration->isEndless());
    }

    private function getEndlessMigrations(bool $hasUnappliedOneTimeMigrations): array
    {
        $endless = array_filter(
            $this->getMigrations(),
            static fn(
                Migration $migration,
            ) => $migration->isEndless(),
        );
        if ($hasUnappliedOneTimeMigrations || !jsmeNaLocale()) {
            return $endless;
        }
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        $alreadyExecuted = $_SESSION['endless_migrations'] ?? [];

        $timestamp = time();

        return array_filter(
            $endless,
            static fn(
                Migration $migration,
            ) => !array_key_exists($migration->getCode(), $alreadyExecuted) ||
                 ($alreadyExecuted[$migration->getCode()] < ($timestamp - 3600)),
        );
    }

    private function apply(
        Migration $migration,
        bool      $silent,
    ): void {
        if ($migration->isEndless() && jsmeNaLocale()) {
            if (!session_id() && !headers_sent()) {
                session_start();
            }
            $_SESSION['endless_migrations']                        ??= [];
            $_SESSION['endless_migrations'][$migration->getCode()] = time();
        }

        if (!$silent) {
            $this->webGui?->confirm();
        }

        if (!$silent) {
            $this->webGui?->writeMessage("Applying migration {$migration->getCode()}");
        }

        // backup db
        if ($this->config->doBackups()) {
            $this->backups->backupBefore($migration);
        }

        // apply migration
        $this->connection->query('BEGIN');
        try {
            $migration->apply();
            // Reset cache — the migration itself may have altered the schema
            $this->hasMigrationPathColumn = null;
            if (!$migration->isEndless()) {
                if ($this->hasTableMigrationsForV2()) {
                    $escapedPath = $this->connection->real_escape_string($migration->getRelativePath());
                    if ($this->hasMigrationPathColumn()) {
                        $this->connection->query(<<<SQL
INSERT IGNORE INTO migrations(migration_path, applied_at) VALUES ('{$escapedPath}', NOW())
SQL,
                        );
                    } else {
                        $this->connection->query(<<<SQL
INSERT IGNORE INTO migrations(migration_code, applied_at) VALUES ('{$migration->getCode()}', NOW())
SQL,
                        );
                    }
                }
            }
            $this->connection->query('COMMIT');
        } catch (\Throwable $throwable) {
            $this->connection->query('ROLLBACK');
            throw $throwable;
        }
    }

    public function run(bool $silent = false)
    {
        $driver              = new \mysqli_driver();
        $oldReportMode       = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

        $hasUnappliedOneTimeMigrations = $this->hasUnappliedMigrations();

        if ($hasUnappliedOneTimeMigrations) {
            if (!$silent) {
                $this->webGui?->configureEnvironment();
            }

            $this->handleUnappliedMigrations($silent);
            $this->handleEndlessMigrations(true);

            if (!$silent) {
                $this->webGui?->cleanupEnvironment();
            }
        }

        $this->handleEndlessMigrations($hasUnappliedOneTimeMigrations);

        $driver->report_mode = $oldReportMode;
    }

}
