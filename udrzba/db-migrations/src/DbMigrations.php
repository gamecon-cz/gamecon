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
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration);
        }
    }

    private function getUnappliedMigrations() {
        return array_filter($this->getMigrations(), function ($migration) {
            $lastId = $this->datastore->get(LAST_APPLIED_MIGRATION_ID) ?? PHP_INT_MIN; // TODO optimize this, it fires one query per each get
            return $migration->getId() > $lastId;
        });
    }

    /**
     * @return Migration[]
     */
    private function getMigrations(): array {
        if (!is_array($this->migrations)) {
            $migrations = [];
            foreach (glob($this->conf->migrationsDirectory . '/*') as $fileName) {
                // 069.php as well as 2021-07-12.php as well as 2021-07-12_01.php
                if (!preg_match('~(\d{3}|\d+-\d+-\d+(?:_\d+)?)[.]php$~', $fileName, $matches)) {
                    continue;
                }

                $migrations[$fileName] = new Migration($fileName, $matches[1], $this->db);
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
        $this->datastore->set(LAST_APPLIED_MIGRATION_ID, $migration->getId());
        $this->datastore->set(LATEST_MIGRATION_HASH, $migration->getHash());
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
