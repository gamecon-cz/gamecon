<?php

namespace Godric\DbMigrations;

define('INITIAL_MIGRATION_HASH', 'initial_migration_hash');
define('LAST_APPLIED_MIGRATION_ID', 'last_applied_migration_id');
define('LATEST_MIGRATION_HASH', 'latest_migration_hash');

class DbMigrations
{

    private $backups;
    private $conf;
    private $datastore;
    private $db;
    private $migrations;
    private $webGui = null;
    private $version;

    public function __construct(DbMigrationsConfig $conf) {
        $this->conf = $conf;

        $this->db = $this->conf->connection;
        $this->datastore = new Datastore($this->db, $this->conf->tableName);
        $this->backups = new Backups($this->db, $this->conf->backupsDirectory);
        if ($this->conf->webGui) {
            $this->webGui = new WebGui;
        }
    }

    private function handleNormalMigrations() {
        if ($this->getVersion() === 1) {
            foreach ($this->getUnappliedMigrationsV1() as $migration) {
                $this->apply($migration);
            }
        } else {
            foreach ($this->getUnappliedMigrationsV2() as $migration) {
                $this->apply($migration);
            }
        }
    }

    private function getVersion(): int {
        if (!$this->version) {
            $query = $this->db->query("SHOW TABLES LIKE 'migrations'");
            if (count($query->fetch_all()) === 0) {
                $this->version = 1;
            } else {
                $this->version = 2;
            }
        }
        return $this->version;
    }

    private function getUnappliedMigrationsV1() {
        return array_filter($this->getMigrations(), function ($migration) {
            $lastId = $this->datastore->get(LAST_APPLIED_MIGRATION_ID) ?? PHP_INT_MIN;
            return is_numeric($migration->getId()) && (int)$migration->getId() > (int)$lastId;
        });
    }

    /**
     * @return Migration[]
     */
    private function getUnappliedMigrationsV2(): array {
        $migrations = $this->getMigrations();
        if (!$migrations) {
            return [];
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

        $this->db->query("DROP TEMPORARY TABLE known_migration_codes_tmp");

        $wrappedUnappliedMigrationCodes = $query->fetch_all();
        $unappliedMigrationCodes = array_column($wrappedUnappliedMigrationCodes, 0);

        return array_filter(
            $this->getMigrations(),
            static function (Migration $migration) use ($unappliedMigrationCodes) {
                return in_array($migration->getId(), $unappliedMigrationCodes, false);
            }
        );
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
        $migration->apply();
        if ($this->getVersion() === 1) {
            $this->datastore->set(LAST_APPLIED_MIGRATION_ID, $migration->getId());
            $this->datastore->set(LATEST_MIGRATION_HASH, $migration->getHash());
        } else {
            $this->db->query("INSERT INTO migrations(migration_code, applied_at) VALUES ('{$migration->getId()}', NOW())");
        }
        $this->db->query('COMMIT');
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
