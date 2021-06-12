<?php

namespace Godric\DbMigrations;

/**
 * Configuration class. Custom attribute values are set by passing associative
 * array to constructor.
 */
class DbMigrationsConfig
{

    /**
     * Mysqli connection with administrative rights to the database. This
     * must be set manually.
     */
    public $connection = null;

    /**
     * Where migration files are stored.
     *
     * Files are named 001.php, 002.php, etc...
     */
    public $migrationsDirectory = './migrations';

    /**
     * Database table DbMigrations stores it's internal data.
     *
     * If it does not exist, it will be created. This table works like key-value store.
     */
    public $tableName = 'db_migrations';

    /**
     * Do backup before each migration?
     */
    public $doBackups = true;

    /**
     * Where to store backups (if enabled).
     *
     * Note, that this is null by default and must be set manually. This
     * is for security reasons - you don't want backups to be stored
     * anywhere default where they can be accessed from the web.
     */
    public $backupsDirectory = null;

    /**
     * Check, if initial migration file contents changed since it was
     * applied to the database.
     *
     * If it did, further action is specified below.
     */
    public $checkInitialMigrationChanges = true;

    /**
     * Check, if last (latest) migrtaion file contents changed since it was
     * applied to the database.
     *
     * If it did, error is reported and execution stops.
     */
    public $checkLastMigrationChanges = true;

    /**
     * If enabled, when initial migration file changes, whole database is
     * dropped, recreated and all migrations applied.
     *
     * Useful for test enviroments.
     */
    public $rewriteDatabaseOnInitialMigrationChange = false;

    /**
     * If enabled, db migrations will show confirmation html form in case
     * there are any unapplied migrations.
     *
     * Until confirmed, no migrations are applied. Ends script execution.
     */
    public $webGui = false;

    // 069.php as well as 2021-07-12.php as well as 2021-07-12_01.php
    public $migrationFilePattern = '~(\d{3}|\d+-\d+-\d+(?:_\d+)?)[.]php$~';

    /**
     * Reads params from associative array and loads them to class
     * attributes.
     */
    public function __construct(array $params) {
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                // TODO this is a little basic implementation - test if attr
                // is public, reject unused attributes from array etc.
                $this->$key = $value;
            } else {
                throw new \LogicException(sprintf("Unknown parameter '%s'", $key));
            }
        }

        if ($this->doBackups && !is_writable($this->backupsDirectory)) {
            throw new \RuntimeException('Backups are enabled but target directory is not set or not writable.');
        }
    }

}
