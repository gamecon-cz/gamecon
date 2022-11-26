<?php

namespace Godric\DbMigrations;

use MySQLDump;

/**
 * Class for backup and restore of database to/from file.
 */
class Backups
{

    private $db;
    private $directory;

    public function __construct($db, $directory) {
        $this->db = $db;
        $this->directory = $directory;
    }

    public function backupBefore(Migration $migration) {
        // skip empty db as workaround for MySQLDump bug
        if (empty($this->getTableNames())) {
            return;
        }

        $dump = new MySQLDump($this->db);
        $dump->save("{$this->directory}/pre-migration-{$migration->getCode()}.sql.gz");
    }

    public function clearDatabase() {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->getTableNames() as $table) {
            $this->db->query("DROP TABLE $table");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function getTableNames() {
        $dbName = $this->db->query('SELECT DATABASE()')->fetch_row()[0];
        if (!$dbName) {
            throw new \Exception('Could not read DB name.');
        }

        $tables = $this->db->query("
          SELECT table_name
          FROM information_schema.tables
          WHERE table_schema = '$dbName';
        ")->fetch_all();

        return array_map(static function (array $row) {
            return $row[0];
        }, $tables);
    }

}
