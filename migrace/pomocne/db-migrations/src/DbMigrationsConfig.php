<?php

namespace Godric\DbMigrations;

/**
 * Configuration class. Custom attribute values are set by passing associative
 * array to constructor.
 */
readonly class DbMigrationsConfig
{
    /**
     * Reads params from associative array and loads them to class
     * attributes.
     */
    public function __construct(
        private \mysqli $connection,
        private string  $migrationsDirectory,
        private string  $tableName = 'db_migrations',
        private bool    $doBackups = true,
        private ?string $backupsDirectory = null,
        private bool    $useWebGui = false,
    )
    {
        if ($this->doBackups && !is_writable($this->backupsDirectory)) {
            throw new \RuntimeException(
                'Backups are enabled but target directory is not set or not writable '
                . var_export($this->backupsDirectory, true),
            );
        }
    }

    public function getConnection(): \mysqli
    {
        return $this->connection;
    }

    public function getMigrationsDirectory(): string
    {
        return $this->migrationsDirectory;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function doBackups(): bool
    {
        return $this->doBackups;
    }

    public function getBackupsDirectory(): ?string
    {
        return $this->backupsDirectory;
    }

    public function useWebGui(): bool
    {
        return $this->useWebGui;
    }

}
