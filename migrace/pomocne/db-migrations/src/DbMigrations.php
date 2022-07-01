<?php

namespace Godric\DbMigrations;

define('INITIAL_MIGRATION_HASH', 'initial_migration_hash');
define('LAST_APPLIED_MIGRATION_ID', 'last_applied_migration_id');
define('LATEST_MIGRATION_HASH', 'latest_migration_hash');

class DbMigrations
{

    private $backups;
    private $conf;
    /** @var \mysqli */
    private $db;
    private $migrations;
    private $webGui = null;
    private $hasTableMigrationsV2 = null;
    private $hasTableMigrationsV1 = null;

    public function __construct(DbMigrationsConfig $conf) {
        $this->conf = $conf;

        $this->db = $this->conf->connection;
        $this->backups = new Backups($this->db, $this->conf->backupsDirectory);
        if ($this->conf->webGui) {
            $this->webGui = new WebGui;
        }
    }

    private function handleNormalMigrations(bool $silent) {
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration, $silent);
        }
    }

    /**
     * @return Migration[]
     */
    private function getUnappliedMigrations(): array {
        $migrations = $this->getMigrations();
        if (!$migrations) {
            return [];
        }

        if (!$this->hasTableMigrationsForV2()) {
            if (!$this->hasTableMigrationsForV1()) {
                return $migrations;
            }
            $migrationsV1 = $this->getMigrationsV1($migrations);
            $unappliedMigrationsV1 = $this->getUnappliedMigrationsV1($migrationsV1);
            $migrationsV2 = $this->getMigrationsV2($migrations);
            return array_merge($unappliedMigrationsV1, $migrationsV2);
        }

        $migrationCodes = array_map(static function (Migration $migration) {
            return $migration->getCode();
        }, $migrations);

        $this->db->query("CREATE TEMPORARY TABLE known_migration_codes_tmp (migration_code VARCHAR(128) PRIMARY KEY)");
        $migrationCodesSql = implode(
            ',',
            array_map(
                static function ($migrationCode) {
                    $escapedCode = dbQv($migrationCode);
                    return "($escapedCode)";
                },
                $migrationCodes
            )
        );
        $this->db->query("INSERT INTO known_migration_codes_tmp (migration_code) VALUES $migrationCodesSql");

        $query = $this->db->query(
            "SELECT known_migration_codes_tmp.migration_code
FROM known_migration_codes_tmp
LEFT JOIN migrations ON migrations.migration_code = known_migration_codes_tmp.migration_code
WHERE migrations.migration_id IS NULL"
        );

        $wrappedUnappliedMigrationCodes = $query->fetch_all();

        $this->db->query("DROP TEMPORARY TABLE known_migration_codes_tmp");

        $unappliedMigrationCodes = array_column($wrappedUnappliedMigrationCodes, 0);

        return array_filter(
            $this->getMigrations(),
            static function (Migration $migration) use ($unappliedMigrationCodes) {
                return in_array($migration->getCode(), $unappliedMigrationCodes, false);
            }
        );
    }

    /**
     * @param Migration[] $migrations
     * @return Migration[]
     */
    private function getMigrationsV1(array $migrations): array {
        return array_filter($migrations, static function (Migration $migration) {
            return $migration->getVersion() === 1;
        });
    }

    /**
     * @param Migration[] $migrationsV1
     * @return Migration[]
     */
    private function getUnappliedMigrationsV1(array $migrationsV1): array {
        $idOfLastAppliedMigrationV1 = $this->getIdOfLastAppliedMigrationV1();

        return array_filter($migrationsV1, static function (Migration $migration) use ($idOfLastAppliedMigrationV1) {
            return $migration->getId() > $idOfLastAppliedMigrationV1;
        });
    }

    private function getIdOfLastAppliedMigrationV1(): int {
        $query = $this->db->query(<<<SQL
SELECT value FROM db_migrations WHERE name = 'last_applied_migration_id'
SQL
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
    private function getMigrationsV2(array $migrations): array {
        return array_filter($migrations, static function (Migration $migration) {
            return $migration->getVersion() === 2;
        });
    }

    private function hasTableMigrationsForV2(): bool {
        if ($this->hasTableMigrationsV2 === true) {
            return true;
        }
        $this->hasTableMigrationsV2 = count($this->db->query("SHOW TABLES LIKE 'migrations'")->fetch_all()) > 0;
        return $this->hasTableMigrationsV2;
    }

    private function hasTableMigrationsForV1(): bool {
        if ($this->hasTableMigrationsV1 === true) {
            return true;
        }
        $this->hasTableMigrationsV1 = count($this->db->query("SHOW TABLES LIKE 'db_migrations'")->fetch_all()) > 0;
        return $this->hasTableMigrationsV1;
    }

    /**
     * @return Migration[]
     */
    private function getMigrations(): array {
        if (!is_array($this->migrations)) {
            $migrations = [];
            foreach (glob($this->conf->migrationsDirectory . '/*.php') as $fileName) {
                $fileBaseName = basename($fileName, '.php');
                if (!preg_match('~^\d.+~', $fileBaseName, $matches)) {
                    continue;
                }

                $migrations[$fileBaseName] = new Migration($fileName, $fileBaseName, $this->db);
            }

            ksort($migrations);

            $this->migrations = array_values($migrations);
        }
        return $this->migrations;
    }

    private function apply(Migration $migration, bool $silent) {
        if (!$silent && $this->webGui) {
            $this->webGui->confirm();
        }

        if (!$silent) {
            echo "Applying migration {$migration->getCode()}.\n";
            @ob_flush();
            flush();
        }

        // backup db
        if ($this->conf->doBackups) {
            $this->backups->backupBefore($migration);
        }

        // apply migration
        $this->db->query('BEGIN');
        try {
            $migration->apply();
            if ($this->hasTableMigrationsForV2()) {
                $this->db->query(<<<SQL
INSERT IGNORE INTO migrations(migration_code, applied_at) VALUES ('{$migration->getCode()}', NOW())
SQL
                );
            }
            $this->db->query('COMMIT');
        } catch (\Throwable $throwable) {
            $this->db->query('ROLLBACK');
            throw $throwable;
        }
    }

    function run(bool $silent = false) {
        $driver = new \mysqli_driver();
        $oldReportMode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
        if (!$silent && $this->webGui) {
            $this->webGui->configureEnviroment();
        }

        $this->handleNormalMigrations($silent);

        if (!$silent && $this->webGui) {
            $this->webGui->cleanupEnviroment();
        }
        $driver->report_mode = $oldReportMode;
    }

}
