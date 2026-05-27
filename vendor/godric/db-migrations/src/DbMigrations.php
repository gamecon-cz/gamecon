<?php

namespace Godric\DbMigrations;

define('INITIAL_MIGRATION_HASH', 'initial_migration_hash');
define('LAST_APPLIED_MIGRATION_ID', 'last_applied_migration_id');
define('LATEST_MIGRATION_HASH', 'latest_migration_hash');

class DbMigrations {

    private
        $backups,
        $conf,
        $datastore,
        $db,
        $migrations,
        $webGui = null;

    function __construct($params) {
        $this->conf = new Config($params);

        $this->db = $this->conf->connection;
        $this->datastore = new Datastore($this->db, $this->conf->tableName);
        $this->backups = new Backups($this->db, $this->conf->backupsDirectory);
        if ($this->conf->webGui) {
            $this->webGui = new WebGui;
        }

        $this->loadMigrations();
    }

    private function apply(Migration $migration) {
        if ($this->webGui) $this->webGui->confirm($migration);

        echo "Applying migration {$migration->getId()}.\n"; // TODO better logging
        @ob_flush();
        flush();

        // backup db
        if ($this->conf->doBackups) $this->backups->backupBefore($migration);

        // apply migration
        $this->db->query('BEGIN');
        $migration->apply(); // TODO or applyTo
        $this->datastore->set(LAST_APPLIED_MIGRATION_ID, $migration->getId());
        $this->datastore->set(LATEST_MIGRATION_HASH, $migration->getHash());
        $this->db->query('COMMIT');
    }

    private function getInitialMigration() {
        return reset($this->migrations);
    }

    private function getLatestMigration() {
        return end($this->migrations);
    }

    private function getUnappliedMigrations() {
        return array_filter($this->migrations, function($migration) {
            $lastId = $this->datastore->get(LAST_APPLIED_MIGRATION_ID) ?? PHP_INT_MIN; // TODO optimize this, it fires one query per each get
            return $migration->getId() > $lastId;
        });
    }

    private function handleInitialMigrationChanges() {
        // checking of initial migration changes disabled
        if (!$this->conf->checkInitialMigrationChanges)
            return;

        $migration = $this->getInitialMigration();

        // no change in initial migration
        if ($migration->getHash() === $this->datastore->get(INITIAL_MIGRATION_HASH))
            return;

        // initial migration changed
        if ($this->conf->rewriteDatabaseOnInitialMigrationChange) {
            // handle all changes
            $this->backups->clearDatabase();
            $this->apply($migration);
            $this->datastore->set(INITIAL_MIGRATION_HASH, $migration->getHash());
        } else {
            // just report error
            throw new \Exception('Initial migration has changed.');
        }
    }

    private function handleLatestMigrationChanges() {
        // checking of lastest migration changes disabled
        if (!$this->conf->checkLastMigrationChanges)
            return;

        $migration = $this->getLatestMigration();

        // latest migration not yet applied
        if ($migration->getId() !== $this->datastore->get(LAST_APPLIED_MIGRATION_ID))
            throw new \Exception('Latest migration is not applied yet.');

        // no change in latest migration
        if ($migration->getHash() === $this->datastore->get(LATEST_MIGRATION_HASH))
            return;

        // latest migration changed
        // TODO just report error, maybe add optional rollback in the future
        throw new \Exception('Latest migration has changed.');
    }

    private function handleNormalMigrations() {
        foreach ($this->getUnappliedMigrations() as $migration) {
            $this->apply($migration);
        }
    }

    private function loadMigrations() {
        $migrations = [];
        foreach (glob($this->conf->migrationsDirectory . '/*') as $f) {
            if (!preg_match('/(\d{3})\.php$/', $f, $m))
                continue;

            $migrations[$f] = new Migration($f, $m[1], $this->db);
        }

        ksort($migrations);

        $this->migrations = array_values($migrations);
    }

    function run() {
        $driver = new \mysqli_driver();
        $oldReportMode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
        if ($this->webGui) $this->webGui->configureEnviroment();

        $this->handleInitialMigrationChanges();
        $this->handleNormalMigrations();
        $this->handleLatestMigrationChanges();

        if ($this->webGui) $this->webGui->cleanupEnviroment();
        $driver->report_mode = $oldReportMode;
    }

}
