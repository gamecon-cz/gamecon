<?php

namespace Godric\DbMigrations;

/**
 * Configuration class. Custom attribute values are set by passing associative
 * array to constructor.
 */
class Config {

    public

        /**
         * Mysqli connection with administrative rights to the database. This
         * must be set manually.
         */
        $connection = null,

        /**
         * Where migration files are stored.
         *
         * Files are named 001.php, 002.php, etc...
         */
        $migrationsDirectory = './migrations',

        /**
         * Database table DbMigrations stores it's internal data.
         *
         * If it does not exist, it will be created. This table works like key-value store.
         */
        $tableName = 'db_migrations',

        /**
         * Do backup before each migration?
         */
        $doBackups = true,

        /**
         * Where to store backups (if enabled).
         *
         * Note, that this is null by default and must be set manually. This
         * is for security reasons - you don't want backups to be stored
         * anywhere default where they can be accessed from the web.
         */
        $backupsDirectory = null,

        /**
         * Check, if initial migration file contents changed since it was
         * applied to the database.
         *
         * If it did, further action is specified below.
         */
        $checkInitialMigrationChanges = true,

        /**
         * Check, if last (latest) migrtaion file contents changed since it was
         * applied to the database.
         *
         * If it did, error is reported and execution stops.
         */
        $checkLastMigrationChanges = true,

        /**
         * If enabled, when initial migration file changes, whole database is
         * dropped, recreated and all migrations applied.
         *
         * Useful for test enviroments.
         */
        $rewriteDatabaseOnInitialMigrationChange = false,

        /**
         * If enabled, db migrations will show confirmation html form in case
         * there are any unapplied migrations.
         *
         * Until confirmed, no migrations are applied. Ends script execution.
         */
        $webGui = false;

    /**
     * Reads params from associative array and loads them to class
     * attributes.
     */
    function __construct($params) {
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                // TODO this is a little basic implementation - test if attr
                // is public, reject unused attributes from array etc.
                $this->$key = $value;
            }
        }

        if ($this->doBackups && !is_writable($this->backupsDirectory)) {
            throw new \Exception('Backups are enabled but target directory is not set or not writable.');
        }
    }

}
