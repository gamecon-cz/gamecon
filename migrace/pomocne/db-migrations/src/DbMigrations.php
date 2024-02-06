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
    private                    $hasTableMigrationsV2 = null;
    private                    $hasTableMigrationsV1 = null;
    private ?array             $unappliedMigrations  = null;

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

    private function handleUnappliedMigrations(bool $silently)
    {
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration, $silently || $migration->isEndless());
        }
    }

    private function handleEndlessMigrations()
    {
        foreach ($this->getEndlessMigrations() as $migration) {
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

            $migrationCodes = array_map(static function (Migration $migration) {
                return $migration->getCode();
            }, $migrations);

            $this->connection->query("CREATE TEMPORARY TABLE known_migration_codes_tmp (migration_code VARCHAR(128) PRIMARY KEY)");
            $migrationCodesSql = implode(
                ',',
                array_map(
                    static function ($migrationCode) {
                        $escapedCode = dbQv($migrationCode);
                        return "($escapedCode)";
                    },
                    $migrationCodes,
                ),
            );
            $this->connection->query("INSERT INTO known_migration_codes_tmp (migration_code) VALUES $migrationCodesSql");

            $query = $this->connection->query(
                "SELECT known_migration_codes_tmp.migration_code
FROM known_migration_codes_tmp
LEFT JOIN migrations ON migrations.migration_code = known_migration_codes_tmp.migration_code
WHERE migrations.migration_id IS NULL",
            );

            $wrappedUnappliedMigrationCodes = $query->fetch_all();

            $this->connection->query("DROP TEMPORARY TABLE known_migration_codes_tmp");

            $unappliedMigrationCodes = array_column($wrappedUnappliedMigrationCodes, 0);

            $this->unappliedMigrations = array_filter(
                $migrations,
                static fn(Migration $migration) => in_array($migration->getCode(), $unappliedMigrationCodes, false),
            );
        }

        return $this->unappliedMigrations;
    }

    /**
     * @param Migration[] $migrations
     * @return Migration[]
     */
    private function getMigrationsV1(array $migrations): array
    {
        return array_filter($migrations, static function (Migration $migration) {
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

        return array_filter($migrationsV1, static function (Migration $migration) use ($idOfLastAppliedMigrationV1) {
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
        return array_filter($migrations, static function (Migration $migration) {
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

    /**
     * @return Migration[]
     */
    private function getMigrations(): array
    {
        if (!is_array($this->migrations)) {
            $migrations = [];
            foreach (glob($this->config->getMigrationsDirectory() . '/*.php') as $fileName) {
                $fileBaseName = basename($fileName, '.php');
                if (!preg_match('~^\d.+~', $fileBaseName, $matches)) {
                    continue;
                }

                $migrations[$fileBaseName] = new Migration(
                    $fileName,
                    $fileBaseName,
                    $this->connection,
                );
            }

            ksort($migrations);

            $this->migrations = array_values($migrations);
        }
        return $this->migrations;
    }

    private function getNormalMigrations(): array
    {
        return array_filter($this->getMigrations(), static fn(Migration $migration) => !$migration->isEndless());
    }

    private function getEndlessMigrations(): array
    {
        return array_filter($this->getMigrations(), static fn(Migration $migration) => $migration->isEndless());
    }

    private function apply(Migration $migration, bool $silent)
    {
        if (!$silent && $this->webGui) {
            $this->webGui->confirm();
        }

        if (!$silent) {
            $this->webGui->writeMessage("Applying migration {$migration->getCode()}.");
        }

        // backup db
        if ($this->config->doBackups()) {
            $this->backups->backupBefore($migration);
        }

        // apply migration
        $this->connection->query('BEGIN');
        try {
            $migration->apply();
            if (!$migration->isEndless()) {
                if ($this->hasTableMigrationsForV2()) {
                    $this->connection->query(<<<SQL
INSERT IGNORE INTO migrations(migration_code, applied_at) VALUES ('{$migration->getCode()}', NOW())
SQL,
                    );
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

        if ($this->hasUnappliedMigrations()) {
            if (!$silent && $this->webGui) {
                $this->webGui->configureEnvironment();
            }

            $this->handleUnappliedMigrations($silent);

            if (!$silent && $this->webGui) {
                $this->webGui->cleanupEnvironment();
            }
        }

        $this->handleEndlessMigrations();

        $driver->report_mode = $oldReportMode;
    }

}
