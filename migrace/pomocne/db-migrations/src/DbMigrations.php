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
    private $hasTableMigrations = null;

    public function __construct(DbMigrationsConfig $conf) {
        $this->conf = $conf;

        $this->db = $this->conf->connection;
        $this->backups = new Backups($this->db, $this->conf->backupsDirectory);
        if ($this->conf->webGui) {
            $this->webGui = new WebGui;
        }
    }

    private function handleNormalMigrations() {
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration);
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

        if (!$this->hasTableMigrations()) {
            return $migrations;
        }

        $migrationCodes = array_map(static function (Migration $migration) {
            return $migration->getId();
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
                return in_array($migration->getId(), $unappliedMigrationCodes, false);
            }
        );
    }

    private function hasTableMigrations(): bool {
        if ($this->hasTableMigrations === true) {
            return true;
        }
        $this->hasTableMigrations = count($this->db->query("SHOW TABLES LIKE 'migrations'")->fetch_all()) > 0;
        return $this->hasTableMigrations;
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

    private function apply(Migration $migration) {
        if ($this->webGui) {
            $this->webGui->confirm();
        }

        echo "Applying migration {$migration->getId()}.\n";
        @ob_flush();
        flush();

        // backup db
        if ($this->conf->doBackups) {
            $this->backups->backupBefore($migration);
        }

        // apply migration
        $this->db->query('BEGIN');
        try {
            $migration->apply();
            if ($this->hasTableMigrations()) {
                $this->db->query(<<<SQL
INSERT IGNORE INTO migrations(migration_code, applied_at) VALUES ('{$migration->getId()}', NOW())
SQL
                );
            }
            $this->db->query('COMMIT');
        } catch (\Throwable $throwable) {
            $this->db->query('ROLLBACK');
            throw $throwable;
        }
    }

    function run() {
        $driver = new \mysqli_driver();
        $oldReportMode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
        if ($this->webGui) {
            $this->webGui->configureEnviroment();
        }

        $this->handleNormalMigrations();

        if ($this->webGui) {
            $this->webGui->cleanupEnviroment();
        }
        $driver->report_mode = $oldReportMode;
    }

}
